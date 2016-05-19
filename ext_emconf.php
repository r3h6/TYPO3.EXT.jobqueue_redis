<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "jobqueue_redis"
 *
 * Auto generated by Extension Builder 2015-12-01
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Jobqueue Redis',
	'description' => 'Redis implementation of job queues for TYPO3 CMS.',
	'category' => 'services',
	'author' => 'R3 H6',
	'author_email' => 'r3h6@outlook.com',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'version' => '1.0.1',
	'constraints' => array(
		'depends' => array(
			'typo3' => '7.6.0-7.6.99',
			'jobqueue' => '1.0.1-1.99.99',
		),
		'conflicts' => array(
			'jobqueue_beanstalkd' => '1.0.0',
		),
		'suggests' => array(
		),
	),
);