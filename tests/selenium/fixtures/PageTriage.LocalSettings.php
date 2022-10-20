<?php

$wgGroupPermissions['autoreviewer']['autopatrol'] = true;
$wgGroupPermissions['patroller']['patrol'] = true;
$wgExtraNamespaces[118] = 'Draft'; // enable AFC
$wgPageTriageDraftNamespaceId = 118; // enable AFC
$wgPageTriageNoIndexUnreviewedNewArticles = true;
$wgPageTriageMaxAge = null;
$wgPageTriageMaxNoIndexAge = 90;
