<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['jobqueue'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['jobqueue'] = [];
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['jobqueue']['R3H6\\JobqueueRedis\\Queue\\RedisQueue'] = [
    'options' => (array) unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]),
];

if (!class_exists('Predis\\Client', true)) {
    require_once \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:' . $_EXTKEY . '/Resources/Private/Vendors/predis.phar');
}
