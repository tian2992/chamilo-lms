<?php
/* For licensing terms, see /license.txt */

/**
 * @package chamilo.social
 *
 * @author Julio Montoya <gugli100@gmail.com>
 */
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

api_block_anonymous_users();

$extraField = new ExtraField('user');
$infoStage = $extraField->get_handler_field_info_by_field_variable('terms_villedustage');
$infoVille = $extraField->get_handler_field_info_by_field_variable('terms_ville');

$tableUser = Database::get_main_table(TABLE_MAIN_USER);
$sql = "SELECT u.id, firstname, lastname, ev.value ville, ev2.value stage
        FROM $tableUser u 
        INNER JOIN extra_field_values ev
        ON ev.item_id = u.id
        INNER JOIN extra_field_values ev2
        ON ev2.item_id = u.id
        WHERE 
            ev.field_id = ".$infoStage['id']." AND 
            ev2.field_id = ".$infoVille['id']." AND 
            u.status = ".STUDENT." AND
            u.active = 1 AND
            (ev.value <> '' OR ev2.value <> '') AND 
            (ev.value LIKE '%::%' OR ev2.value LIKE '%::%')            
";


$cacheDriver = new \Doctrine\Common\Cache\ApcuCache();
$keyDate = 'map_cache_date';
$keyData = 'map_cache_data';

$now = time();

// Refresh cache every day
$tomorrow = strtotime('+1 day', $now);
$tomorrow = strtotime('+5 minute', $now);

$loadFromDatabase = true;
if ($cacheDriver->contains($keyData) && $cacheDriver->contains($keyDate)) {
    $savedDate = $cacheDriver->fetch($keyDate);
    if ($savedDate < $now) {
        $loadFromDatabase = true;
    } else {
        $loadFromDatabase = false;
    }
}

$loadFromDatabase = true;

if ($loadFromDatabase) {
    $result = Database::query($sql);
    $data = Database::store_result($result, 'ASSOC');

    $cacheDriver->save($keyData, $data);
    $cacheDriver->save($keyDate, $tomorrow);
} else {
    $data = $cacheDriver->fetch($keyData);
}

foreach ($data as &$result) {
    $result['complete_name'] = addslashes(api_get_person_name($result['firstname'], $result['lastname']));
    $parts = explode('::', $result['ville']);
    if (isset($parts[1]) && !empty($parts[1])) {
        $parts2 = explode(',', $parts[1]);

        $result['ville_lat'] = $parts2[0];
        $result['ville_long'] = $parts2[1];

        unset($result['ville']);
    }

    $parts = explode('::', $result['stage']);
    if (isset($parts[1]) && !empty($parts[1])) {
        $parts2 = explode(',', $parts[1]);
        $result['stage_lat'] = $parts2[0];
        $result['stage_long'] = $parts2[1];
        unset($result['stage']);
    }
}


$apiKey = api_get_configuration_value('google_api_key');
$htmlHeadXtra[] = '<script type="text/javascript" src="'.api_get_path(WEB_LIBRARY_JS_PATH).'map/markerclusterer.js"></script>';
$htmlHeadXtra[] = '<script type="text/javascript" src="'.api_get_path(WEB_LIBRARY_JS_PATH).'map/oms.min.js"></script>';

$tpl = new Template(null);
$tpl->assign('url', api_get_path(WEB_CODE_PATH).'social/profile.php');
$tpl->assign(
    'image_city',
    Display::return_icon(
        'red-dot.png',
        '',
        [],
        ICON_SIZE_SMALL,
        false,
        true
    )
);
$tpl->assign(
    'image_stage',
    Display::return_icon(
        'blue-dot.png',
        '',
        [],
        ICON_SIZE_SMALL,
        false,
        true
    )
);

$tpl->assign('places', json_encode($data));
$tpl->assign('api_key', $apiKey);


$layout = $tpl->get_template('social/map.tpl');
$tpl->display($layout);