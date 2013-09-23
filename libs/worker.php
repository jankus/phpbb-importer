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
namespace QA\PhpbbImporter;
use PDO;
require_once 'text-to-slug.php';
require_once 'bbcode-parser.php';
require_once QA_INCLUDE_DIR.'qa-app-posts.php';
require_once QA_INCLUDE_DIR.'qa-db-admin.php';
require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';

class Worker
{

    public static $_code = 'phpbb-importer';
    public static $_connection = null;
    public static $_users = null;
    public static $_usersOld2New = null;
    public static $_forums = null;
    public static $_forumsOld2New = null;
    public static $_topics = null;
    public static $_topicsOld2New = null;
    public static $_posts = null;
    public static $_postsOld2New = null;
    public static $_localUrlPlaceholder = '<!--local-->';

    public static function setDbData($host, $name, $user, $pass, $prefix, $charset = 'utf8')
    {
        $_SESSION[self::$_code]['db']['host'] = $host;
        $_SESSION[self::$_code]['db']['name'] = $name;
        $_SESSION[self::$_code]['db']['user'] = $user;
        $_SESSION[self::$_code]['db']['pass'] = $pass;
        $_SESSION[self::$_code]['db']['prefix'] = $prefix;
        $_SESSION[self::$_code]['db']['charset'] = $charset;
    }

    public static function clearData()
    {
        self::$_connection = null;
        self::$_users = null;
        self::$_forums = null;
        self::$_topics = null;
        self::$_posts = null;
        unset($_SESSION[self::$_code]);
    }

    public static function getIsDbSet()
    {
        return self::$_connection;
    }

    public static function getDb()
    {
        try {
            self::$_connection = new \PDO(
            'mysql:dbname='.self::getDbName().';host='.self::getDbHost(),
                self::getDbUser(),
                self::getDbPass(),
                array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET CHARACTER SET \''.self::getDbCharset().'\'') // TODO: and/or add SET NAMES?
            );
        } catch (PDOException $e) {
            return 'Connection failed: ' . $e->getMessage();
        }
        return self::$_connection;
    }

    public static function getDbHost()
    {
        $host = $_SESSION[self::$_code]['db']['host'];
        if ($host) {
            return $host;
        }
        return null;
    }

    public static function getDbName()
    {
        $name = $_SESSION[self::$_code]['db']['name'];
        if ($name) {
            return $name;
        }
        return null;
    }

    public static function getDbUser()
    {
        $user = $_SESSION[self::$_code]['db']['user'];
        if ($user) {
            return $user;
        }
        return null;
    }

    public static function getDbPass()
    {
        $pass = $_SESSION[self::$_code]['db']['pass'];
        if ($pass) {
            return $pass;
        }
        return '';
    }

    public static function getDbPrefix()
    {
        $prefix = $_SESSION[self::$_code]['db']['prefix'];
        if ($prefix) {
            return $prefix;
        }
        return '';
    }

    public static function getDbCharset()
    {
        $charset = $_SESSION[self::$_code]['db']['charset'];
        if ($charset) {
            return $charset;
        }
        return null;
    }

    public static function setData($handle, $value)
    {
        $_SESSION[self::$_code]['data'][$handle] = $value;
    }

    public static function getData($handle)
    {
        if (isset($_SESSION[self::$_code]['data'][$handle])) {
            return $_SESSION[self::$_code]['data'][$handle];
        }
        return null;
    }

    public static function getDataUsers()
    {
        if (self::$_users) {
            return self::$_users;
        } else {
            $db = self::$_connection;
            if ($db) {
                // getting users that will be authors on Q2A
                $sql = "SELECT user_id, user_email, user_regdate, user_ip, username, user_lastvisit, user_lang
                        FROM ".self::getDbPrefix()."users
                        WHERE user_email <> '' AND user_id NOT IN (SELECT ban_userid FROM ".self::getDbPrefix()."banlist) "; // only not banned!
                foreach ($db->query($sql) as $row) {
                    $userData[] = $row;
                }
                self::$_users = $userData;
                return self::$_users;
            }
        }
        return null;
    }

    public static function pushDataUsers()
    {
        $userData = self::getDataUsers();
        $db = self::$_connection;
        foreach ($userData as $user) {
            set_time_limit(30);
            /*
             * Example implementation with single sign on, when users should be created on external database
             *
            $newUser = null;
            // selecting verified user from IP database
            $sql = "SELECT id, name
                    FROM ".DB_PREF."m_community_user
                    WHERE email  = '".mysql_real_escape_string($user['user_email'])."' AND verified = 1";
            foreach ($db->query($sql) as $row) {
                $newUser = $row;
            }
            if (isset($newUser['id'])) {
                $qaUserId = $newUser['id'];
                if (!$newUser['name'] && $user['username']) { // in IP db name is missing so we will update it!
                    $sql = "UPDATE ".DB_PREF."m_community_user
                            SET name = '".mysql_real_escape_string($user['username'])."'
                            WHERE id = '".mysql_real_escape_string($newUser['id'])."'
                            ";
                    $db->query($sql);
                }
            } else { // if user doesn't exist we will create it
                $sql = "INSERT INTO ".DB_PREF."m_community_user
                        (email, name, verified, created_on, last_login, ip, newsletterEmail)
                        VALUES (
                        '".mysql_real_escape_string($user['user_email'])."',
                        '".mysql_real_escape_string($user['username'])."',
                        1,
                        FROM_UNIXTIME(".mysql_real_escape_string($user['user_regdate'])."),
                        FROM_UNIXTIME(".mysql_real_escape_string($user['user_lastvisit'])."),
                        '".mysql_real_escape_string($user['user_ip'])."',
                        '".mysql_real_escape_string($user['user_email'])."'
                        )";
                $db->query($sql);
                //print_r($db->errorInfo());
                $qaUserId = $db->lastInsertId();
            }
            */
            $qaEmail = $user['user_email'];
            // TODO: extract passwords from phpBB
            $qaPassword = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!£$%^&*()_+?/|@;:<>,.'), 0, 8); // getting random 8 characters
            $qaHandle = $user['username'];
            $qaUserLevel = QA_USER_LEVEL_BASIC;
            $qaIsUserConfirmed = true; // set to false to automatically send email confirmation emails
            $qaUserId = \qa_create_new_user($qaEmail, $qaPassword, $qaHandle, $qaUserLevel, $qaIsUserConfirmed);
            // converting phpBB users to IP
            self::$_usersOld2New[$user['user_id']] = $qaUserId;
        }
        return count(self::$_usersOld2New);
    }

    public static function getDataForums()
    {
        if (self::$_forums) {
            return self::$_forums;
        } else {
            $db = self::$_connection;
            if ($db) {
                // getting forums (categories)
                $sql = "SELECT forum_id, forum_name, forum_desc, parent_id, left_id
                        FROM ".self::getDbPrefix()."forums
                        WHERE 1
                        ORDER BY parent_id, left_id "; // TODO: get the correct order if parents are also mixed
                foreach ($db->query($sql) as $row) {
                    $forumData[] = $row;
                }
                self::$_forums = $forumData;
                return self::$_forums;
            }
        }
        return null;
    }

    public static function pushDataForums()
    {
        $forumData = self::getDataForums();
        $forumToCategory = array();
        foreach ($forumData as $forum) {
            set_time_limit(30);
            $qaParentId = $forum['parent_id'] ? self::$_forumsOld2New[$forum['parent_id']] : null;
            $qaCategoryName = html_entity_decode($forum['forum_name']); // phpBB stores already transformed content
            $qaCategorySlug = Text2slug::convert($qaCategoryName);
            $qaCategoryContent = $forum['forum_desc'];
            $qaCategoryId = \qa_db_category_create($qaParentId, $qaCategoryName, $qaCategorySlug);
            \qa_db_category_set_content($qaCategoryId, $qaCategoryContent);
            // converting forum ID to category ID
            self::$_forumsOld2New[$forum['forum_id']] = $qaCategoryId;
        }
        return count(self::$_forumsOld2New);
    }

    public static function getDataTopics()
    {
        if (self::$_topics) {
            return self::$_topics;
        } else {
            $db = self::$_connection;
            if ($db) {
                // getting topics (questions)
                $sql = "SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_poster, t.topic_time, t.topic_views, t.topic_first_post_id, p.post_text, p.post_attachment, p.bbcode_bitfield, p.bbcode_uid
                        FROM ".self::getDbPrefix()."topics t, ".self::getDbPrefix()."posts p
                        WHERE t.topic_approved = 1 AND t.topic_moved_id = 0 AND t.topic_first_post_id = p.post_id"; // not moved ones!
                foreach ($db->query($sql) as $row) {
                    $topicData[] = $row;
                }
                self::$_topics = $topicData;
                return self::$_topics;
            }
        }
        return null;
    }

    public static function pushDataTopics()
    {
        $topicData = self::getDataTopics();
        foreach ($topicData as $topic) {
            set_time_limit(30);
            $attachments = null;
            if ($topic['post_attachment']) {
                $attachments = self::getDataAttachments($topic['topic_first_post_id']);
            }

            $qaType = 'Q'; // question
            $qaParentId = null; // does not follow another answer
            $qaTitle = html_entity_decode($topic['topic_title']); // phpBB stores already transformed content
            $qaContent = BbcodeParser::parse($topic['post_text'], $topic['bbcode_uid'], $topic['bbcode_bitfield'], $attachments);
            $qaFormat = 'html';
            $qaCategoryId = self::$_forumsOld2New[$topic['forum_id']];
            $qaTags = null;
            $qaUserId = self::$_usersOld2New[$topic['topic_poster']];

            $qaPostId = \qa_post_create($qaType, $qaParentId, $qaTitle, $qaContent, $qaFormat, $qaCategoryId, $qaTags, $qaUserId);
            \qa_post_set_created($qaPostId, $topic['topic_time']); // setting created time to original

            // updating views count
            \qa_db_query_sub(
                "UPDATE ^posts SET views = # WHERE postid = #",
                $topic['topic_views'], $qaPostId
            );
            // we changed views count so hotness should be recalculated
            \qa_db_hotness_update($qaPostId);

            self::$_topicsOld2New[$topic['topic_id']] = $qaPostId;
        }
        return count(self::$_topicsOld2New);
    }

    public static function getDataPosts()
    {
        if (self::$_posts) {
            return self::$_posts;
        } else {
            $db = self::$_connection;
            if ($db) {
                // getting posts (questions and answers)
                $sql = "SELECT p.post_id, p.topic_id, p.poster_id, p.post_time, p.post_text, p.post_attachment, p.bbcode_bitfield, p.bbcode_uid
                        FROM ".self::getDbPrefix()."posts p
                        WHERE post_approved = 1 AND post_id NOT IN (SELECT topic_first_post_id FROM ".self::getDbPrefix()."topics)"; // only replies!
                foreach ($db->query($sql) as $row) {
                    $topicData[] = $row;
                }
                self::$_posts = $topicData;
                return self::$_posts;
            }
        }
        return null;
    }

    public static function pushDataPosts()
    {
        $postData = self::getDataPosts();
        $count = 0;
        foreach ($postData as $post) {
            set_time_limit(30);
            $attachments = null;
            if ($post['post_attachment']) {
                $attachments = self::getDataAttachments($post['post_id']);
            }

            $qaType = 'A'; // answer
            $qaParentId = self::$_topicsOld2New[$post['topic_id']];
            $qaTitle = null;
            $qaContent = BbcodeParser::parse($post['post_text'], $post['bbcode_uid'], $post['bbcode_bitfield'], $attachments);
            $qaFormat = 'html';
            $qaCategoryId = null;
            $qaTags = null;
            $qaUserId = self::$_usersOld2New[$post['poster_id']];

            $qaPostId = \qa_post_create($qaType, $qaParentId, $qaTitle, $qaContent, $qaFormat, $qaCategoryId, $qaTags, $qaUserId);
            \qa_post_set_created($qaPostId, $post['post_time']); // setting created time to original
            self::$_postsOld2New[$post['post_id']] = $qaPostId;
        }
        return count(self::$_postsOld2New);
    }

    public static function getDataAttachments($postId)
    {
        if (is_numeric($postId) && $postId > 0) {
            $db = self::$_connection;
            if ($db) {
                // getting posts (questions and answers)
                $sql = "SELECT attach_id, real_filename, attach_comment
                        FROM ".self::getDbPrefix()."attachments
                        WHERE post_msg_id = ".mysql_real_escape_string($postId);
                foreach ($db->query($sql) as $row) {
                    $attachments[] = $row;
                }
                return $attachments;
            }
        }
        return null;
    }

    public static function convertLocalUrls()
    {
        $posts = \qa_db_read_all_assoc(qa_db_query_sub(
            "SELECT postid, type, title, content FROM ^posts WHERE content LIKE $",
            '%'.self::$_localUrlPlaceholder.'%'
        ));
        Debug::log('Posts to convert:'.count($posts));
        $count = 0;
        foreach ($posts as $post) {
            set_time_limit(30);

            $content = self::replaceLocalUrls($post['content'], $post['postid']);
            if ($content != $post['content']) { $count++; }
            \qa_db_query_sub(
                "UPDATE ^posts SET content = $ WHERE postid = #",
                $content, $post['postid']
            );
        }

        Debug::log('Posts modified:'.$count);
        return $count;
    }

    public static function replaceLocalUrls($content, $postId)
    {
        $logText = '';

        // looking for all local urls
        preg_match_all('#'.self::$_localUrlPlaceholder.'.*?'.self::$_localUrlPlaceholder.'#ms', $content, $locals);

        $logText .= 'PostId:'.$postId;
        $logText .= "\n".'Links in text:'.count($locals[0]);
        foreach ($locals[0] as $local) {
            set_time_limit(30);

            $logText .= "\n".'Found:'.$local;
            // looking for topic id
            preg_match('#'.self::$_localUrlPlaceholder.'.*?href=".*?[\?\&;]t=(\d+).*?".*?'.self::$_localUrlPlaceholder.'#', $local, $findtopic);
            $logText .= "\n".'TopicID:'.(isset($findtopic[1]) ? $findtopic[1] : 'null');
            $questionId = isset($findtopic[1]) && isset(self::$_topicsOld2New[$findtopic[1]]) ? self::$_topicsOld2New[$findtopic[1]] : null;
            $logText .= ' QuestionID:'.($questionId ? $questionId : 'null');
            if ($questionId) { // if topic id is found we can convert the link
                $title = null;
                $global = true;
                // looking for post id
                preg_match('#'.self::$_localUrlPlaceholder.'.*?href=".*?[\?\&;]p=(\d+).*?".*?'.self::$_localUrlPlaceholder.'#', $local, $findpost);
                $logText .= "\n".'PostID:'.(isset($findpost[1]) ? $findpost[1] : 'null');
                $answerId = isset($findpost[1]) && isset(self::$_postsOld2New[$findpost[1]]) ? self::$_postsOld2New[$findpost[1]] : null;
                $logText .= ' AnswerID:'.($answerId ? $answerId : 'null');
                $newUrl = null;
                if ($answerId) { // if post id is found we will create a direct link to it
                    $type = "A"; // (A)nswer or (C)omment
                    $newUrl = \qa_q_path($questionId, $title, $global, $type, $answerId);
                    $logText .= "\n".'NewURL(to answer):'.$newUrl;
                } else {
                    $newUrl = \qa_q_path($questionId, $title, $global);
                    $logText .= "\n".'NewURL(to topic):'.$newUrl;
                }
                $before = $content;
                $content = str_replace($local, '<a href="'.$newUrl.'">'.$newUrl.'</a>', $content);
                if ($before != $content) { $logText .= "\n".'Content updated!'; }
            } else { // placeholder exists but topic ID was not found; it could be a link to forum or directly to post
                $logText .= "\n".'URL cannot be converted. Cleaning up placeholders.';
                $content = str_replace(self::$_localUrlPlaceholder, '', $content);
            }
        }
        Debug::log($logText);
        return $content;
    }
}

class Debug
{
    public static function log($text)
    {
        $file = Worker::$_code.'-log.txt';
        if (is_array($text)) { $text = var_export($text, true); }
        $text .= "\n\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }
}