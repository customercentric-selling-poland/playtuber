<?php
// +------------------------------------------------------------------------+
// | @author Deen Doughouz (DoughouzForest)
// | @author_url 1: http://www.playtubescript.com
// | @author_url 2: http://codecanyon.net/user/doughouzforest
// | @author_email: wowondersocial@gmail.com
// +------------------------------------------------------------------------+
// | PlayTube - The Ultimate Video Sharing Platform
// | Copyright (c) 2017 PlayTube. All rights reserved.
// +------------------------------------------------------------------------+


require_once('./assets/init.php');
$page = 'login';

if (IS_LOGGED == true) {
	header("Location: " . PT_Link(''));
	exit();
}

if (file_exists("./sources/$page/content.php")) {
    include("./sources/$page/content.php");
}

if (empty($pt->content)) {
    include("./sources/404/content.php");
}

$side_header = 'not-logged';

$announcement_html = '';
$footer            = '';


$final_content = PT_LoadPage('container', array(
    'CONTAINER_TITLE' => $pt->title,
    'CONTAINER_DESC' => $pt->description,
    'CONTAINER_KEYWORDS' => $pt->keyword,
    'CONTAINER_CONTENT' => $pt->content,
    'ANNOUNCEMENT'     => $announcement_html,
    'IS_LOGGED' => (IS_LOGGED == true) ? 'data-logged="true"' : '',
    'MAIN_URL' => $pt->actual_link,

    'HEADER_LAYOUT' => PT_LoadPage('header/content', array(
        'SIDE_HEADER' => PT_LoadPage("header/$side_header"),
        'SEARCH_KEYWORD' => (!empty($_GET['keyword'])) ? PT_Secure($_GET['keyword']) : ''
    )),
    'FOOTER_LAYOUT' => $footer,
    'OG_METATAGS' => $og_meta,
    'EXTRA_JS' => PT_LoadPage('extra-js/content'),
    'MODE' => (($pt->mode == 'night') ? 'checked' : ''),

    'RIGHT_AD' => PT_GetAd('right_side'),
    'LEFT_AD' => PT_GetAd('left_side'),
    'FOOTER_AD' => ($pt->page != 'register' && $pt->page != 'login') ? PT_GetAd('footer') : '',
    'HEADER_AD' => PT_GetAd('header'),
));

echo $final_content;
$db->disconnect();
unset($pt);
?>
