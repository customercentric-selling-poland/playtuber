<?php
if (empty($_GET['id'])) {
    header("Location: " . PT_Link('404'));
    exit();
}

$id = PT_Secure($_GET['id']);
if (strpos($id, '_') !== false) {
    $id_array = explode('_', $id);
    $id_html  = $id_array[1];
    $id       = str_replace('.html', '', $id_html);
}
$get_video = PT_GetVideoByID($id, 1, 1);
$is_found = $db->where('lang_key',$get_video->category_id)->getValue(T_LANGS,'COUNT(*)');
if ($is_found == 0) {
    $db->where('id',$get_video->id)->update(T_VIDEOS,array('category_id' => 'other','sub_category' => ''));
    $get_video->category_name = $categories['other'];
    $get_video->category_id = 'other';
}

if (empty($get_video)) {
    header("Location: " . PT_Link('404'));
    exit();
}

$pt->page_url_ = $pt->config->site_url.'/hello/'.PT_Slug($get_video->title, $get_video->video_id);
if (empty($get_video->short_id)) {
    $short_id = PT_GenerateKey(6, 6);
    $update_short_id = $db->where('id', $get_video->id)->update(T_VIDEOS, array('short_id' => $short_id));
    $get_video->short_id = $short_id;
}


$get_video->age = false;
if ($get_video->age_restriction == 2) {
    if (!IS_LOGGED) {
        $get_video->age = true;
    } else {
        if (($get_video->user_id != $user->id) && !is_age($user->id)) {
            $get_video->age = true;
        }
    }
}

$pt->video_approved = true;

if ($pt->config->approve_videos == 'on' || ($pt->config->auto_approve_ == 'no' && $get_video->sell_video)) {
    if ($get_video->approved == 0) {
        $pt->video_approved = false;
    }
}

$pt->video_type = 'public';

if ($get_video->privacy == 1) {
    if (!IS_LOGGED) {
        $pt->video_type = 'private';
    } else if (($get_video->user_id != $user->id) && ($user->admin == 0)) {
        $pt->video_type = 'private';
    }
}
$pt->is_paid = 0;

$user_data = $get_video->owner;

$desc = str_replace('"', "'", $get_video->edit_description);
$desc = str_replace('<br>', "", $desc);
$desc = str_replace('<br/>', "", $desc);
$desc = str_replace("\n", "", $desc);
$desc = str_replace("\r", "", $desc);
$desc = mb_substr($desc, 0, 220, "UTF-8");

$pt->get_video   = $get_video;
$pt->page        = 'hello';
$pt->title       = $get_video->title;
$pt->description = htmlspecialchars($desc);
$pt->keyword     = $get_video->tags;
$pt->is_list     = false;
$pt->is_wl       = false;
$pt->get_id      = $id;
$pt->list_name   = "";
$list_id         = 0;
$pt->video_owner = (IS_LOGGED && $get_video->user_id == $user->id);
$pt->reported    = false;
$pt->converted   = true;

if ($pt->config->ffmpeg_system == 'on' && $pt->get_video->converted != 1) {
    $pt->converted = false;
}


if (!empty($_GET['list']) && $_GET['list'] == 'wl' && IS_LOGGED) {
    $user_id   = $pt->user->id;
    $pt->is_wl = (($db->where('video_id', $get_video->id)->where('user_id', $user_id)->getValue(T_WLATER, 'count(*)') > 0));
    if (!$pt->is_wl) {
        header("Location: " . PT_Link("hello/$id"));
        exit();
    }
    $pt->page_url_ = $pt->config->site_url.'/hello/'.PT_Slug($get_video->title, $get_video->video_id).'/list/'.$_GET['list'];

}

else if (!empty($_GET['list'])) {
    $list_id     = PT_Secure($_GET['list']);
    $pt->is_list = (
        ($db->where('list_id', $list_id)->getValue(T_LISTS, 'count(*)') > 0) &&
        ($db->where('list_id', $list_id)->where('video_id', $get_video->id)->getValue(T_PLAYLISTS, 'count(*)') > 0)
    );

    if (!$pt->is_list) {
        header("Location: " . PT_Link("hello/$id"));
        exit();
    }
    $pt->page_url_ = $pt->config->site_url.'/hello/'.PT_Slug($get_video->title, $get_video->video_id).'/list/'.$_GET['list'];
}

if (!in_array($get_video->id, $_SESSION['next_video'])) {
    $_SESSION['next_video'][] = $get_video->id;
}

$related_videos = array();

$not_ids = '';
$not_in = '';
$not_in_query = '';

$query_video_title = PT_Secure($get_video->title);
$related_videos    =  '';
$video_sidebar  = '';
$next_video     = '';
$next           = 0;
$list_sidebar   = '';
$list_user_name = '';
$list_count     = 0;
$video_index    = 0;
$pt->list_owner = false;


if($pt->is_list === true) {

    $pt->list_data  = $db->where("list_id", $list_id)->getOne(T_LISTS);
    $pt->list_name  = $pt->list_data->name;
    $videos         = $db->where('list_id', $list_id)->get(T_PLAYLISTS, 300, 'video_id');
    $video_list     = array();
    $list_count     = count($videos);
    $list_user_data = PT_UserData($pt->list_data->user_id);

    if (!empty($list_user_data)) {
        $list_user_name = $list_user_data->name;
    }

    if (IS_LOGGED === true && ($pt->list_data->user_id == $pt->user->id)) {
        $pt->list_owner = true;
    }

    foreach ($videos as $vid) {
        $video_list[] = $vid->video_id;
    }

    $play_list_videos = $db->where('id', array_values($video_list), 'IN')->orderBy('id','asc',array_values($video_list))->get(T_VIDEOS);
    $vid_number       = 1;
    foreach ($play_list_videos as $key => $pl_vid) {
        $pl_vid         = PT_GetVideoByID($pl_vid, 0, 0, 0);
        $pl_vid->url    = PT_Link('hello/' . PT_Slug($pl_vid->title, $pl_vid->video_id) . "/list/$list_id");
        $list_sidebar .= PT_LoadPage('hello/video-list', array(
            'TITLE' => $pl_vid->title,
            'URL' => $pl_vid->url,
            'LIST_ID' => $list_id,
            'VID_ID' => $pl_vid->id,
            'ID' => $pl_vid->video_id,
            'THUMBNAIL' => $pl_vid->thumbnail,
            'VID_NUMBER' => ($pl_vid->video_id == $id) ? "<i class='fa fa-circle'></i>" : $vid_number,
            'VIDEO_ID_' => PT_Slug($pl_vid->title, $pl_vid->video_id)
        ));
        if ($pl_vid->video_id == $id) {
            $video_index = $vid_number;
        }
        $vid_number++;
    }
}


$comments = '';

$checked = 'checked';
$ad_media = '';
$ad_link = '';
$ad_skip = 'false';
$ad_skip_num = 0;
$is_video_ad = '';
$is_vast_ad = '';
$vast_url = '';
$vast_type = '';
$last_ads = 0;
$ad_image = '';
$ad_link = '';
$sidebar_ad = PT_GetAd('hello_side_bar');
$is_pro  = false;
$user_ad_trans = '';
$ad_desc = '';
$ads_sys = ($pt->config->user_ads == 'on') ? true : false;
$vid_monit = true;

if ($pt->config->usr_v_mon == 'on') {
    $vid_monit = ($user_data->video_mon == 0) ? false : true;
}

if (!empty($_COOKIE['last_ads_seen']) && !$is_pro) {
    if ($_COOKIE['last_ads_seen'] > (time() - 600)) {
        $last_ads = 1;
    }
}

$last_ads = 0;
if ($last_ads == 0 && !$is_pro && $ads_sys) {
    $rand      = (rand(0,1)) ? rand(0,1) :(rand(0,1) ? : rand(0,1));

    if ($rand == 0) {
        $get_random_ad = $db->where('active', 1)->orderBy('RAND()')->getOne(T_VIDEO_ADS);
        $sidebar_ad    = PT_GetAd('hello_side_bar');
        if (!empty($get_random_ad)) {

            if (!empty($get_random_ad->ad_media)) {
                $ad_media = $get_random_ad->ad_media;
                $ad_link = PT_Link('redirect/' . $get_random_ad->id . '?type=video');
                $is_video_ad = ",'ads'";
            }

            if (!empty($get_random_ad->vast_xml_link)) {
                $vast_url = $get_random_ad->vast_xml_link;
                $vast_type = $get_random_ad->vast_type;
                $is_vast_ad = ",'vast'";
            }

            if ($get_random_ad->skip_seconds > 0) {
                $ad_skip = 'true';
                $ad_skip_num = $get_random_ad->skip_seconds;
            }

            if (!empty($get_random_ad->ad_image)) {
                $ad_image = $pt->ad_image = $get_random_ad->ad_image;
                $ad_link = PT_Link('redirect/' . $get_random_ad->id . '?type=image');
            }

            $update_clicks = $db->where('id', $get_random_ad->id)->update(T_VIDEO_ADS, array(
                'views' => $db->inc(1)
            ));
            $cookie_name = 'last_ads_seen';
            $cookie_value = time();
            setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
        }
    }

    else if ($rand == 1 && $vid_monit) {
        $user_ads      = pt_get_user_ads();
        // echo  $db->getLastQuery();
        // exit();
        if (!empty($user_ads)) {
            $get_random_ad =  $user_ads;
            $random_ad_id  = $get_random_ad->id;
            $ad_skip       = 'true';
            $ad_link       = urldecode($get_random_ad->url);
            $ad_skip_num   = 5;

            if ($user_ads->type == 1) {
                $user_ad_trans   = "rad-transaction";
                $_SESSION['ua_'] = $random_ad_id;
                $_SESSION['vo_'] = $get_video->user_id;
            }

            else{
                pt_register_ad_views($random_ad_id,$get_video->user_id);
                $db->insert(T_ADS_TRANS,array('type' => 'view', 'ad_id' => $random_ad_id, 'video_owner' => $get_video->user_id, 'time' => time()));
            }

            if ($user_ads->category == 'video') {
                $ad_media      = PT_GetMedia($get_random_ad->media);
                $is_video_ad   = ",'ads'";
                $ad_desc       = PT_LoadPage("ads/includes/d-overlay",array(
                    "AD_TITLE" => PT_ShortText($user_ads->headline,40),
                    "AD_DESC" => PT_ShortText($user_ads->description,70),
                    "AD_URL" => urldecode($user_ads->url),
                    "AD_URL_NAME" => pt_url_domain(urldecode($user_ads->url)),
                ));
            }

            else if ($user_ads->category == 'image') {
                $ad_image = $pt->ad_image = PT_GetMedia($get_random_ad->media);
            }


            $cookie_name = 'last_ads_seen';
            $cookie_value = time();
            setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
        }
    }
    $rand2      = (rand(0,1)) ? rand(0,1) :(rand(0,1) ? : rand(0,1));
    $sidebar_ad    = PT_GetAd('hello_side_bar');
    // Get side bar ads
    if ($rand2 == 1){
        $sidebarAd              = pt_get_user_ads(2);
        if (!empty($sidebarAd)) {
            $get_random_ad      = $sidebarAd;
            $random_ad_id       = $get_random_ad->id;
            $_SESSION['pagead'] = $random_ad_id;
            $sidebar_ad    = PT_LoadPage('ads/includes/sidebar',array(
                'ID' => $random_ad_id,
                'IMG' => PT_GetMedia($get_random_ad->media),
                'TITLE' => PT_ShortText($get_random_ad->headline,30),
                'NAME' => PT_ShortText($get_random_ad->name,20),
                'DESC' => PT_ShortText($get_random_ad->description,70),
                'URL' => PT_Link("redirect/$random_ad_id?type=pagead"),
                'URL_NAME' => pt_url_domain(urldecode($get_random_ad->url))
            ));
        }
    }
}

$pt->video_240 = 0;
$pt->video_360 = 0;
$pt->video_480 = 0;
$pt->video_720 = 0;
$pt->video_1080 = 0;
$pt->video_2048 = 0;
$pt->video_4096 = 0;

if ($pt->config->ffmpeg_system == 'on') {
    $explode_video = explode('_video', $get_video->video_location);
    if ($get_video->{"240p"} == 1) {
        $pt->video_240 = $explode_video[0] . '_video_240p_converted.mp4';
    }
    if ($get_video->{"360p"} == 1) {
        $pt->video_360 = $explode_video[0] . '_video_360p_converted.mp4';
    }
    if ($get_video->{"480p"} == 1) {
        $pt->video_480 = $explode_video[0] . '_video_480p_converted.mp4';
    }
    if ($get_video->{"720p"} == 1) {
        $pt->video_720 = $explode_video[0] . '_video_720p_converted.mp4';
    }
    if ($get_video->{"1080p"} == 1) {
        $pt->video_1080 = $explode_video[0] . '_video_1080p_converted.mp4';
    }
    if ($get_video->{"4096p"} == 1) {
        $pt->video_4096 = $explode_video[0] . '_video_4096p_converted.mp4';
    }
    if ($get_video->{"2048p"} == 1) {
        $pt->video_2048 = $explode_video[0] . '_video_2048p_converted.mp4';
    }
}
$content_page = (($pt->is_list === true) ? "playlist" : (($pt->is_wl === true) ? "watch-later" : "content"));
if (!empty($get_video->youtube)) {
    $vast_url = '';
    $vast_type = '';
    $ad_media = '';
    $ad_link = '';
    $ad_skip = 0;
    $ad_skip_num = 0;
    $is_video_ad = '';
    $ad_desc = '';
    $is_vast_ad = '';
    $ad_image = '';
    $pt->ad_image = '';
    $user_ad_trans = '';
}

$currency         = "";
$pt->user_data = $user_data;

$pt->in_queue = false;
if ($get_video->converted != 1) {

    $is_in_queue = $db->where('video_id',$get_video->id)->getValue(T_QUEUE,'COUNT(*)');
    $process_queue = $db->getValue(T_QUEUE,'video_id',$pt->config->queue_count);
    if ($pt->config->queue_count == 1) {
        if ($process_queue != $get_video->id) {
            $pt->in_queue = true;
        }
    }
    elseif ($pt->config->queue_count > 1) {
        if ($is_in_queue > 0 && !in_array($get_video->id, $process_queue)) {
            $pt->in_queue = true;
        }
    }

}

$pt->sub_category = '';
if (!empty($get_video->sub_category)) {
    foreach ($pt->sub_categories as $cat_key => $subs) {
        foreach ($subs as $sub_key => $sub_value) {
            if ($get_video->sub_category == array_keys($sub_value)[0]) {
                $pt->sub_category = $sub_value[array_keys($sub_value)[0]];
            }
        }
    }
}

$pt->continent_hide = false;
if (!empty($get_video->geo_blocking) && $pt->config->geo_blocking == 'on') {
    $blocking_array = json_decode($get_video->geo_blocking);
    if ((empty($_COOKIE['r']) || !in_array(base64_decode($_COOKIE['r']), $pt->continents)) && !PT_IsAdmin() && !$pt->video_owner) {
        $pt->continent_hide = true;
    }
    else if (in_array(base64_decode($_COOKIE['r']), $blocking_array) && !PT_IsAdmin() && !$pt->video_owner) {
        $pt->continent_hide = true;
    }
}

$video_type = 'video/mp4';

if (!empty($get_video->youtube)) {
    $video_type = 'video/youtube';
}


$pt->content = PT_LoadPage("hello/$content_page", array(
    'ID' => $get_video->id,
    'KEY' => $get_video->video_id,
    'THUMBNAIL' => $get_video->thumbnail,
    'TITLE' => $get_video->title,
    'DESC' => $get_video->markup_description,
    'URL' => $get_video->url,
    'VIDEO_LOCATION_240' => $pt->video_240,
    'VIDEO_LOCATION' => $get_video->video_location,
    'VIDEO_LOCATION_360' => $pt->video_360,
    'VIDEO_LOCATION_480' => $pt->video_480,
    'VIDEO_LOCATION_720' => $pt->video_720,
    'VIDEO_LOCATION_1080' => $pt->video_1080,
    'VIDEO_LOCATION_4096' => $pt->video_4096,
    'VIDEO_LOCATION_2048' => $pt->video_2048,
    'VIDEO_TYPE' => $video_type,
    'VIDEO_MAIN_ID' => $get_video->video_id,
    'VIDEO_ID' => $get_video->video_id_,
    'USER_DATA' => $user_data,
    'SUBSCIBE_BUTTON' => PT_GetSubscribeButton($user_data->id),
    'VIDEO_SIDEBAR' => $video_sidebar,
    'LIST_SIDEBAR' => $list_sidebar,
    'LIST_OWNERNAME' => $list_user_name,
    'VID_INDEX' => $video_index,
    'LIST_COUNT' => $list_count,
    'LIST_NAME' => $pt->list_name,
    'VIDEO_NEXT_SIDEBAR' => $next_video,
    'COOKIE' => $checked,
    'VIEWS' => number_format($get_video->views),
    'LIKES' => number_format($get_video->likes),
    'DISLIKES' => number_format($get_video->dislikes),
    'LIKES_P' => $get_video->likes_percent,
    'DISLIKES_P' => $get_video->dislikes_percent,
    'RAEL_LIKES' => $get_video->likes,
    'RAEL_DISLIKES' => $get_video->dislikes,
    'ISLIKED' => ($get_video->is_liked > 0) ? 'liked="true"' : '',
    'ISDISLIKED' => ($get_video->is_disliked > 0) ? 'disliked="true"' : '',
    'LIKE_ACTIVE_CLASS' => ($get_video->is_liked > 0) ? 'active' : '',
    'DIS_ACTIVE_CLASS' => ($get_video->is_disliked > 0) ? 'active' : '',

    'VIDEO_COMMENTS' => '',

    'SAVED_BUTTON' => $save_button,
    'IS_SAVED' => ($is_saved > 0) ? 'saved="true"' : '',
    'ENCODED_URL' => urlencode($get_video->url),
    'CATEGORY' => $get_video->category_name,
    'CATEGORY_ID' => $get_video->category_id,
    'TIME' => $get_video->time_alpha,
    'VAST_URL' => $vast_url,
    'VAST_TYPE' => $vast_type,
    'AD_MEDIA' => "'$ad_media'",
    'AD_LINK' => "'$ad_link'",
    'AD_P_LINK' => "$ad_link",
    'AD_SKIP' => $ad_skip,
    'AD_SKIP_NUM' => $ad_skip_num,
    'ADS' => $is_video_ad,
    'USER_ADS_DESC_OVERLAY' => $ad_desc,
    'VAT' => $is_vast_ad,
    'AD_IMAGE' => $ad_image,
    'COMMENT_AD' => PT_GetAd('hello_comments'),
    'WATCH_SIDEBAR_AD' => $sidebar_ad,
    'USR_AD_TRANS' => $user_ad_trans,
    'CURRENCY'   => $currency,
    'SUB_CATEGORY' => $pt->sub_category,
    'VIDEO_ID_' => $get_video->video_id,

));
