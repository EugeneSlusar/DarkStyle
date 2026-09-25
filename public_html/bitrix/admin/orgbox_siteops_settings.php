<?php

// Administrative proxy for orgBox: SiteOps. The module installer creates the
// same file for new installations; keeping it in the project also supports
// already installed modules after a deployment update.
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/orgbox.siteops/admin/settings.php';
