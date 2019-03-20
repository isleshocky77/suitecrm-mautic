<?php

global $sugar_version;

$admin_option_defs=array();

$admin_option_defs['Administration']['mautic'] = array('helpInline','LBL_MAUTIC_SETUP','LBL_MAUTIC_DESCRIPTION','./index.php?module=Mautic&action=OauthSetup');

$admin_group_header[] = array('LBL_MAUTIC', '', false, $admin_option_defs, '');
