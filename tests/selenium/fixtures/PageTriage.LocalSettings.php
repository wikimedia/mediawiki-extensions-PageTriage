<?php

$wgGroupPermissions['autoreviewer']['autopatrol'] = true;
$wgGroupPermissions['patroller']['patrol'] = true;
// enable AFC
$wgExtraNamespaces[118] = 'Draft';
// enable AFC
$wgPageTriageDraftNamespaceId = 118;
$wgPageTriageNoIndexUnreviewedNewArticles = true;
$wgPageTriageMaxAge = null;
$wgPageTriageMaxNoIndexAge = 90;
