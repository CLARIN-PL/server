<?php

use OCP\AppFramework\App;

$app = new App('clarin');
//\OCP\Util::addScript('core', 'https://ctj.clarin-pl.eu/clarin_bar/script.js');
\OCP\Util::addScript('clarin', 'wsProgressShower');
\OCP\Util::addStyle('clarin', 'customStyles');
//$container = $app->getContainer();
//
//$container->query('OCP\INavigationManager')->add(function () use ($container) {
//	$urlGenerator = $container->query('OCP\IURLGenerator');
//	$l10n = $container->query('OCP\IL10N');
//	return [
//		// the string under which your app will be referenced in Nextcloud
//		'id' => 'clarin',
//
//		// sorting weight for the navigation. The higher the number, the higher
//		// will it be listed in the navigation
//		'order' => 10,
//
//		// the route that will be shown on startup
////		'href' => $urlGenerator->linkToRoute('clarin.page.index'),
//
//		// the icon that will be shown in the navigation
//		// this file needs to exist in img/
//		'icon' => $urlGenerator->imagePath('clarin', 'app.svg'),
//
//		// the title of your application. This will be used in the
//		// navigation or on the settings page of your app
//		'name' => $l10n->t('Clarin'),
//	];
//});