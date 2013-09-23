<?php
/*
	Plugin Name: phpBB Importer
	Plugin URI:
	Plugin Description: Imports phpBB forum to your Q2A site.
	Plugin Version: 1.0
	Plugin Date: 2012-11-12
	Plugin Author: ImpressPages CMS team
	Plugin Author URI: http://www.impresspages.org/
	Plugin License: Commercial
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI:
*/
include_once 'libs/worker.php';

class qa_phpbb_importer
{
    function suggest_requests() // for display in admin interface
    {
        return array(
            array(
                'title' => 'phpBB Importer',
                'request' => 'phpbb-importer',
                'nav' => 'O', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
            ),
        );
    }

    function match_request($request)
    {
        $parts=qa_request_parts();
        return ($parts[0]=='phpbb-importer');
    }

    function process_request($request)
    {
        // TODO: do AJAX for progress
        // TODO: check versions for compatibility
        // TODO: check whether Q2A DB is empty

        $action=qa_request_part(1);

        $qa_content=qa_content_prepare();

        $qa_content['title']='phpBB Importer (beta)';

        // return error if user doesn't have enough privileges
        if (qa_get_logged_in_level() < QA_USER_LEVEL_SUPER) {
            $qa_content['error'] = "You don't have privileges to view this page.";
            return $qa_content;
        }

        $qa_content['custom'] = '<p style="font-size: 20px;"><strong>Steps:</strong>';
        $qa_content['custom'] .= !$action ? ' <strong>Beginning</strong>,' : ' Beginning,';
        $qa_content['custom'] .= $action == 'start' ? ' <strong>Start</strong>,' : ' Start,';
        $qa_content['custom'] .= $action == 'process' ? ' <strong>Process</strong>,' : ' Process,';
        $qa_content['custom'] .= $action == 'convert' ? ' <strong>Convert</strong>' : ' Convert';
        $qa_content['custom'] .= '</p>';

        switch ($action) {
            case 'start':
                $clearDbSession = false;
                $errors = array();

                // user clicked "Start import"
                if (qa_post_text('startimport')) {
                    if (!qa_post_text('phpbburl')) {
                        $errors['phpbburl'] = true;
                    }
                    \QA\PhpbbImporter\Worker::setDbData(
                        qa_post_text('dbhost'),
                        qa_post_text('dbname'),
                        qa_post_text('dbuser'),
                        qa_post_text('dbpass'),
                        qa_post_text('dbprefix')
                    );
                    \QA\PhpbbImporter\Worker::setData('phpbburl', qa_post_text('phpbburl'));
                    \QA\PhpbbImporter\Worker::setData('convertlocalurls', qa_post_text('convertlocalurls') ? true : false);
                    $db = \QA\PhpbbImporter\Worker::getDb();
                    // if connection is correct and errors list is empty moving to the processing
                    if (is_object($db)) {
                        if (count($errors) == 0) {
                            qa_redirect(qa_request_part(0).'/process');
                        }
                    } else {
                        $qa_content['error'] = $db;
                        $clearDbSession = true;
                    }
                } else {
                    $clearDbSession = true;
                }

                // cleaning session information and waiting for new data
                if ($clearDbSession) {
                    \QA\PhpbbImporter\Worker::clearData();
                }

                $qa_content['form']=array(
                    'tags' => 'METHOD="POST" ACTION="'.qa_self_html().'"',
                    'style' => 'wide',
                    // 'ok' => qa_post_text('startimport') ? 'Import started' : null,
                    'title' => 'phpBB database connection',
                    'fields' => array(
                        'dbhost' => array(
                            'label' => 'Server',
                            'tags' => 'NAME="dbhost"',
                            'value' => qa_post_text('host') ? qa_post_text('dbhost') : QA_MYSQL_HOSTNAME,
                            'error' => true ? '' : qa_html('error'),
                        ),
                        'dbname' => array(
                            'label' => 'DB name',
                            'tags' => 'NAME="dbname"',
                            'value' => qa_post_text('dbname') ? qa_post_text('dbname') : QA_MYSQL_DATABASE,
                            'error' => true ? '' : qa_html('error'),
                        ),
                        'dbuser' => array(
                            'label' => 'DB user',
                            'tags' => 'NAME="dbuser"',
                            'value' => qa_post_text('dbuser') ? qa_post_text('dbuser') : QA_MYSQL_USERNAME,
                            'error' => true ? '' : qa_html('error'),
                        ),
                        'dbpass' => array(
                            'label' => 'DB user password',
                            'tags' => 'NAME="dbpass"',
                            'value' => qa_post_text('dbpass') ? qa_post_text('dbpass') : QA_MYSQL_PASSWORD,
                            'error' => true ? '' : qa_html('error'),
                        ),
                        'dbprefix' => array(
                            'label' => 'DB prefix',
                            'tags' => 'NAME="dbprefix"',
                            'value' => qa_post_text('dbprefix') ? qa_post_text('dbprefix') : 'phpbb_',
                            'error' => true ? '' : qa_html('error'),
                        ),
                        'phpbburl' => array(
                            'label' => 'Absolute URL to PHPBB installation',
                            'tags' => 'NAME="phpbburl"',
                            'value' => qa_post_text('phpbburl'),
                            'error' => isset($errors['phpbburl']) ? qa_html('This field cannot be empty!') : '',
                        ),
                        'convertlocalurls' => array(
                            'type' => 'checkbox',
                            'label' => 'Convert local URLs',
                            'tags' => 'NAME="convertlocalurls"',
                            'value' => qa_post_text('convertlocalurls'),
                            'error' => true ? '' : qa_html('error'),
                        ),
                    ),
                    'buttons' => array(
                        'ok' => array(
                            'tags' => 'NAME="startimport"',
                            'label' => 'Start the import',
                            'value' => '1',
                        ),
                    ),
                );
                break;
            case 'process':
                // if DB data is correct method will create a static variable for connection
                \QA\PhpbbImporter\Worker::getDb();

                // is DB is not set or not working returning back to the start
                if (!\QA\PhpbbImporter\Worker::getIsDbSet()) {
                    qa_redirect(qa_request_part(0).'/start');
                }

                $qa_content['custom_process'] = '
                <p><strong>Found:</strong></p>
                <ul>
                    <li>Users: '.$cUsers = count(\QA\PhpbbImporter\Worker::getDataUsers()).'</li>
                    <li>Forums: '.$cForums = count(\QA\PhpbbImporter\Worker::getDataForums()).'</li>
                    <li>Topics: '.$cTopics = count(\QA\PhpbbImporter\Worker::getDataTopics()).'</li>
                    <li>Posts: '.$cPosts = count(\QA\PhpbbImporter\Worker::getDataPosts()).'</li>
                </ul>
                ';

                $totalEntries = $cUsers + $cForums + $cTopics + $cPosts;
                $timePerEntry = 0.15;
                $potencialTimeConsumption = round($totalEntries * $timePerEntry, 0);
                $qa_content['custom_process'] .= '<p>Conversion may take up to '.$potencialTimeConsumption.' seconds or more. <br /><strong>Make DB backups and make sure there are enough PHP memory and execution time on your server before converting.</strong></p>';
                $qa_content['custom_process'] .= '<p><a href="'.qa_path_html(qa_request_part(0).'/convert').'">Convert</a> - click and patiently wait until page reloads.</p>';
                // TODO: set countdown timer by $potencialTimeConsumption

                break;
            case 'convert':
                $start = (float) array_sum(explode(' ',microtime()));
                $qa_content['custom_convert'] = '<p>Started...</p>';

                // if DB data is correct method will create a static variable for connection
                \QA\PhpbbImporter\Worker::getDb();

                // is DB is not set or not working returning back to the start
                if (!\QA\PhpbbImporter\Worker::getIsDbSet()) {
                    qa_redirect(qa_request_part(0).'/start');
                }

                $qa_content['custom_convert'] .= '
                <p><strong>Found:</strong></p>
                <ul>
                    <li>Users: '.count(\QA\PhpbbImporter\Worker::getDataUsers()).'</li>
                    <li>Forums: '.count(\QA\PhpbbImporter\Worker::getDataForums()).'</li>
                    <li>Topics: '.count(\QA\PhpbbImporter\Worker::getDataTopics()).'</li>
                    <li>Posts: '.count(\QA\PhpbbImporter\Worker::getDataPosts()).'</li>
                </ul>
                ';

                // converting users
                $usersConverted = \QA\PhpbbImporter\Worker::pushDataUsers();
                $qa_content['custom_convert'] .= '<p><strong>'.$usersConverted.'</strong> users converted.</p>';
                \QA\PhpbbImporter\Debug::log('Users OldId to NewId');
                \QA\PhpbbImporter\Debug::log(\QA\PhpbbImporter\Worker::$_usersOld2New);

                // converting forums to categories
                $forumsConverted = \QA\PhpbbImporter\Worker::pushDataForums();
                $qa_content['custom_convert'] .= '<p><strong>'.$forumsConverted.'</strong> forums converted to categories.</p>';
                \QA\PhpbbImporter\Debug::log('Forums OldId to Category NewId');
                \QA\PhpbbImporter\Debug::log(\QA\PhpbbImporter\Worker::$_forumsOld2New);

                // converting topics to questions
                $topicsConverted = \QA\PhpbbImporter\Worker::pushDataTopics();
                $qa_content['custom_convert'] .= '<p><strong>'.$topicsConverted.'</strong> topics converted to questions.</p>';
                \QA\PhpbbImporter\Debug::log('Topics OldId to Questions NewId');
                \QA\PhpbbImporter\Debug::log(\QA\PhpbbImporter\Worker::$_topicsOld2New);

                // converting posts to answers
                $answersConverted = \QA\PhpbbImporter\Worker::pushDataPosts();
                $qa_content['custom_convert'] .= '<p><strong>'.$answersConverted.'</strong> posts converted to answers.</p>';
                \QA\PhpbbImporter\Debug::log('Posts OldId to Answers NewId');
                \QA\PhpbbImporter\Debug::log(\QA\PhpbbImporter\Worker::$_postsOld2New);

                \QA\PhpbbImporter\Debug::log("\n\n".'Local URLs conversion');
                if (\QA\PhpbbImporter\Worker::getData('convertlocalurls')) {
                    $postsConverted = \QA\PhpbbImporter\Worker::convertLocalUrls();
                    $qa_content['custom_convert'] .= '<p>Local URLs were changed in <strong>'.$postsConverted.'</strong> posts.</p>';
                }

                $end = (float) array_sum(explode(' ',microtime()));
                $qa_content['custom_convert'] .= '<p>Finished... Conversion time: '. sprintf("%.4f", ($end-$start)).' seconds.</p>';
                break;
            default:
                $qa_content['custom_default'] = '<p>Works only with these versions: phpBB3.0 and Q2A 1.5</p>';
                $qa_content['custom_default'] .= '<p><a href="'.qa_path_html(qa_request_part(0).'/start').'">Start</a></p>';
                break;
        }

        return $qa_content;
    }

}
