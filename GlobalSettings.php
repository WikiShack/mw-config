<?php

use MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaCacheStore;
use MediaWiki\FileRepo\ForeignDBViaLBRepo;

$wgHooks['CreateWikiGenerateDatabaseLists'][] = 'WikiShackFunctions::onGenerateDatabaseLists';
$wgHooks['ManageWikiCoreAddFormFields'][] = 'WikiShackFunctions::onManageWikiCoreAddFormFields';
$wgHooks['ManageWikiCoreFormSubmission'][] = 'WikiShackFunctions::onManageWikiCoreFormSubmission';
$wgHooks['MediaWikiServices'][] = 'WikiShackFunctions::onMediaWikiServices';
$wgHooks['BeforePageDisplay'][] = static function ( &$out, &$skin ) {
	if ( $out->getTitle()->isSpecialPage() ) {
		$out->setRobotPolicy( 'noindex,nofollow' );
	}
	return true;
};

// Extensions
if ( $wi->dbname !== 'ldapwikiwiki' ) {
	wfLoadExtensions([
		'CentralAuth',
		'GlobalPreferences',
		'GlobalBlocking',
		'RemovePII',
	]);

	// Only allow users with global accounts to login
	$wgCentralAuthStrict = true;
	$wgCentralAuthEnableSul3 = true;

	$wgCentralAuthAutoLoginWikis = $wmgCentralAuthAutoLoginWikis;

	if ( isset( $wgAuthManagerAutoConfig['primaryauth'][LocalPasswordPrimaryAuthenticationProvider::class] ) ) {
		$wgAuthManagerAutoConfig['primaryauth'][LocalPasswordPrimaryAuthenticationProvider::class]['args'][0]['loginOnly'] = true;
	}

	$wgPasswordConfig['null'] = [ 'class' => InvalidPassword::class ];

	$wgLoginNotifyUseCentralId = true;
	$wgWebAuthnNewCredsDisabled = true;
	$wgCentralAuthSharedDomainCallback = static fn ( $dbname ) =>
	"https://{$wi->getSharedDomain()}/$dbname";

	if ( $wmgSharedDomainPathPrefix ) {
		$wgCentralAuthCookieDomain = '';
		$wgCookiePrefix = 'auth';
		$wgSessionName = 'authSession';
		$wgWebAuthnNewCredsDisabled = false;

		$wgCheckUserClientHintsEnabled = true;
		$wgCheckUserAlwaysSetClientHintHeaders = true;
	}
}

if ( $wi->isExtensionActive( 'chameleon' ) ) {
	wfLoadExtension( 'Bootstrap' );
}

if ( $wi->isExtensionActive( 'StandardDialogs' ) ) {
	wfLoadExtension( 'OOJSPlus' );
}

if ( $wgWikiShackCommons && !$cwPrivate ) {
	wfLoadExtension( 'GlobalUsage' );
}

if ( $wi->isExtensionActive( 'InterwikiSorting' ) ) {
	$wgInterwikiSortingInterwikiSortOrders = include __DIR__ . '/InterwikiSortOrders.php';
}

if ( $wi->isExtensionActive( 'Popups' ) ) {
	if ( $wmgShowPopupsByDefault ) {
		$wgPopupsHideOptInOnPreferencesPage = true;
		$wgPopupsOptInDefaultState = '1';
		$wgPopupsOptInStateForNewAccounts = '1';
		$wgPopupsReferencePreviewsBetaFeature = false;
	}
}


if ( $wi->isExtensionActive( 'SocialProfile' ) ) {
	require_once "$IP/extensions/SocialProfile/SocialProfile.php";
	//$wgSocialProfileFileBackend = 'wikishack-swift';
	$wgUserBoardAllowPrivateMessages = false;
}

if ( $wi->isExtensionActive( 'VisualEditor' ) ) {
	$wgUseRestbaseVRS = false;
	$wgVisualEditorDefaultParsoidClient = 'direct';
	if ( $wmgVisualEditorEnableDefault ) {
		$wgDefaultUserOptions['visualeditor-enable'] = 1;
		$wgDefaultUserOptions['visualeditor-editor'] = 'visualeditor';
	} else {
		$wgDefaultUserOptions['visualeditor-enable'] = 0;
		$wgDefaultUserOptions['visualeditor-editor'] = 'wikitext';
	}
}

if ( $wi->isExtensionActive( 'CodeMirror' ) ) {
	$wgDefaultUserOptions['usecodemirror'] = (int)$wmgCodeMirrorEnableDefault;
}

if ( $wi->isAnyOfExtensionsActive( 'WikibaseClient', 'WikibaseRepository' ) ) {
	// Includes Wikibase Configuration. There is a global and per-wiki system here.
	require_once '/srv/mediawiki/config/Wikibase.php';
}

$wgVirtualRestConfig = [
	'modules' => [
		'parsoid' => [
			'url' => 'https://meta.wikishack.com/w/rest.php',
			'domain' => $wi->server,
			'prefix' => $wi->dbname,
			'forwardCookies' => (bool)$cwPrivate,
			'restbaseCompat' => false,
		],
	],
	'global' => [
		'domain' => $wgCanonicalServer,
		'timeout' => 360,
		'forwardCookies' => false,
		'HTTPProxy' => null,
	],
];

// Article paths
$articlePath = str_replace( '$1', '', $wgArticlePath );

$wgDiscordNotificationWikiUrl = $wi->server . $articlePath;
$wgDiscordNotificationWikiUrlEnding = '';
$wgDiscordNotificationWikiUrlEndingDeleteArticle = '?action=delete';
$wgDiscordNotificationWikiUrlEndingDiff = '?diff=prev&oldid=';
$wgDiscordNotificationWikiUrlEndingEditArticle = '?action=edit';
$wgDiscordNotificationWikiUrlEndingHistory = '?action=history';
$wgDiscordNotificationWikiUrlEndingUserRights = 'Special:UserRights?user=';

/** TODO:
 * Add to ManageWiki (core)
 * Add rewrites to decode.php and index.php
 */
$wgActionPaths['view'] = $wgArticlePath;

// ?action=raw is not supported by this
// according to documentation
$actions = [
	'delete',
	'edit',
	'history',
	'info',
	'markpatrolled',
	'protect',
	'purge',
	'render',
	'revert',
	'rollback',
	'submit',
	'unprotect',
	'unwatch',
	'watch',
];

foreach ( $actions as $action ) {
	$wgActionPaths[$action] = $wgArticlePath . '?action=' . $action;
}

if ( ( $wgMirahezeActionPathsFormat ?? 'default' ) !== 'default' ) {
	switch ( $wgMirahezeActionPathsFormat ) {
		case 'specialpages':
			$wgActionPaths['edit'] = $articlePath . 'Special:EditPage/$1';
			$wgActionPaths['submit'] = $wgActionPaths['edit'];
			$wgActionPaths['delete'] = $articlePath . 'Special:DeletePage/$1';
			$wgActionPaths['protect'] = $articlePath . 'Special:ProtectPage/$1';
			$wgActionPaths['unprotect'] = $wgActionPaths['protect'];
			$wgActionPaths['history'] = $articlePath . 'Special:PageHistory/$1';
			$wgActionPaths['info'] = $articlePath . 'Special:PageInfo/$1';
			break;
		case '$1/action':
		case 'action/$1':
			foreach ( $actions as $action ) {
				$wgActionPaths[$action] = $articlePath . str_replace( 'action', $action, $wgMirahezeActionPathsFormat );
			}

			break;
	}
}

// Don't need globals here
unset( $actions, $articlePath );

$wgAllowedCorsHeaders[] = 'X-WikiShack-Debug';

// Closed Wikis
if ( $cwClosed ) {
	$wgRevokePermissions = [
		'*' => [
			'block' => true,
			'createaccount' => true,
			'delete' => true,
			'edit' => true,
			'protect' => true,
			'import' => true,
			'upload' => true,
			'undelete' => true,
		],
	];

	if ( $wi->isExtensionActive( 'Comments' ) ) {
		$wgRevokePermissions['*']['comment'] = true;
	}
}

// Public Wikis
if ( !$cwPrivate ) {
	$wgDiscordIncomingWebhookUrl = $wmgGlobalDiscordWebhookUrl;
	$wgDiscordExperimentalWebhook = $wmgDiscordExperimentalWebhook;
}

if ( !$wmgSharedDomainPathPrefix ) {
	// Dynamic cookie settings dependant on $wgServer
	foreach ( $wi->getAllowedDomains() as $domain ) {
		if ( preg_match( '/' . preg_quote( $domain ) . '$/', $wi->server ) ) {
			$wgCentralAuthCookieDomain = '.' . $domain;
			$wgMFStopRedirectCookieHost = '.' . $domain;
			break;
		} else {
			$wgCentralAuthCookieDomain = '';
			if ( $wi->isExtensionActive( 'MobileFrontend' ) ) {
				$host = parse_url( $wi->server, PHP_URL_HOST );
				$wgMFStopRedirectCookieHost = $host !== false ? $host : null;

				// Don't need a global here
				unset( $host );
			}
		}
	}
}

// DataDump
$wgDataDumpDirectory = '/srv/static/dumps/';

$wgDataDump = [
	'xml' => [
		'file_ending' => '.xml.gz',
		'useBackendTempStore' => true,
		'chunkSize' => 512 * 1024 * 1024,
		'startChunkSize' => 1 * 1024 * 1024 * 1024,
		'generate' => [
			'type' => 'mwscript',
			'script' => 'dumpBackup',
			'options' => [
				'--full',
				'--logs',
				'--uploads',
				'--output',
				'gzip:/tmp/${filename}',
			],
			'arguments' => [
				'--namespaces'
			],
		],
		'limit' => 1,
		'permissions' => [
			'view' => 'view-dump',
			'generate' => 'generate-dump',
			'delete' => 'delete-dump',
		],
		'htmlform' => [
			'name' => 'namespaceselect',
			'type' => 'namespaceselect',
			'exists' => true,
			'noArgsValue' => 'all',
			'hide-if' => [ '!==', 'generatedumptype', 'xml' ],
			'label-message' => 'datadump-namespaceselect-label'
		],
	],
	'zip' => [
		'file_ending' => '.zip',
		'generate' => [
			'type' => 'script',
			'script' => '/usr/bin/zip',
			'options' => [
				'-r',
				"{$wgDataDumpDirectory}" . '${filename}',
				($cwPrivate ? "/srv/static/private/{$wgDBname}" : "/srv/static/{$wgDBname}"),  // 条件による切り替え
			],
		],
		'limit' => 1,
		'permissions' => [
			'view' => 'view-dump',
			'generate' => 'generate-dump',
			'delete' => 'delete-dump',
		],
	],
];

// Email
$wgSMTP = [
	'host' => 'mail.wikishack.com',
	'IDHost' => 'wikishack.com',
	'port' => 2587,
	'username' => 'noreply@wikishack.com',
	'password' => $wgWikiShackEmailPassword,
	'auth' => true,
];

if ( !$wi->isExtensionActive( 'wikiseo' ) ) {
	$wgSkinMetaTags = [ 'og:title', 'og:type' ];
}

// $wgForeignFileRepos
if ( $wmgEnableSharedUploads && $wmgSharedUploadDBname && in_array( $wmgSharedUploadDBname, $wgLocalDatabases ) ) {
	if ( !$wmgSharedUploadBaseUrl || $wmgSharedUploadBaseUrl === $wmgSharedUploadDBname ) {
		$wmgSharedUploadSubdomain = substr( $wmgSharedUploadDBname, 0, -4 );

		$wmgSharedUploadBaseUrl = "{$wmgSharedUploadSubdomain}.wikishack.com";
	}

	$wgForeignFileRepos[] = [
		'class' => ForeignDBViaLBRepo::class,
		'name' => "shared-{$wmgSharedUploadDBname}",
		'url' => "https://static.wikishack.com/{$wmgSharedUploadDBname}",
		'hashLevels' => 2,
		'thumbScriptUrl' => false,
		'transformVia404' => true,
		'hasSharedCache' => true,
		'descBaseUrl' => "https://{$wmgSharedUploadBaseUrl}/wiki/File:",
		'scriptDirUrl' => "https://{$wmgSharedUploadBaseUrl}/w",
		'fetchDescription' => true,
		'descriptionCacheExpiry' => 86400 * 7,
		'wiki' => $wmgSharedUploadDBname,
		'initialCapital' => true,
		'abbrvThreshold' => 160
	];
}

// WikiShack Commons
if ( $wgDBname !== 'commonswiki' && $wgWikiShackCommons && strpos( wfHostname(), 'mwbeta' ) === false ) {
	$wgForeignFileRepos[] = [
		'class' => ForeignDBViaLBRepo::class,
		'name' => 'wikishackcommons',
		'url' => 'https://static.wikishack.com/commonswiki',
		'hashLevels' => 2,
		'thumbScriptUrl' => false,
		'transformVia404' => true,
		'hasSharedCache' => true,
		'descBaseUrl' => 'https://commons.wikishack.com/wiki/File:',
		'scriptDirUrl' => 'https://commons.wikishack.com/w',
		'fetchDescription' => true,
		'descriptionCacheExpiry' => 86400 * 7,
		'wiki' => 'commonswiki',
		'initialCapital' => true,
		'abbrvThreshold' => 160
	];
}

if ( $wgDBname !== 'commonswikibeta' && $wgWikiShackCommons && strpos( wfHostname(), 'mwbeta' ) !== false ) {
	$wgForeignFileRepos[] = [
		'class' => ForeignDBViaLBRepo::class,
		'name' => 'wikishackcommons',
		'url' => 'https://static.wikishack.com/commonswikibeta',
		'hashLevels' => 2,
		'thumbScriptUrl' => false,
		'transformVia404' => true,
		'hasSharedCache' => true,
		'descBaseUrl' => 'https://commons.wikishack.dev/wiki/File:',
		'scriptDirUrl' => 'https://commons.wikishack.dev/w',
		'fetchDescription' => true,
		'descriptionCacheExpiry' => 86400 * 7,
		'wiki' => 'commonswikibeta',
		'initialCapital' => true,
		'abbrvThreshold' => 160
	];
}

// $wgLogos
$wgLogos = [
	'1x' => $wgLogo,
];

$wgApexLogo = [
	'1x' => $wgLogos['1x'],
	'2x' => $wgLogos['1x'],
];

if ( $wgIcon ) {
	$wgLogos['icon'] = $wgIcon;
}

if ( $wgWordmark ) {
	$wgLogos['wordmark'] = [
		'src' => $wgWordmark,
		'width' => $wgWordmarkWidth,
		'height' => $wgWordmarkHeight,
	];
}

// $wgUrlShortenerAllowedDomains
$wgUrlShortenerAllowedDomains = [
	'(.*\.)?wikishack\.com'
];

if ( preg_match( '/(wikishack)\.dev$/', $wi->server ) ) {
	$wgUrlShortenerAllowedDomains = [
		'(.*\.)?wikishack\.dev'
	];
}

// JsonConfig
if ( $wi->isExtensionActive( 'JsonConfig' ) ) {
	$wgJsonConfigs = [
		'Map.JsonConfig' => [
			'namespace' => 486,
			'nsName' => 'Data',
			// page name must end in ".map", and contain at least one symbol
			'pattern' => '/.\.map$/',
			'license' => 'CC-BY-SA 4.0',
			'isLocal' => false,
		],
		'Tabular.JsonConfig' => [
			'namespace' => 486,
			'nsName' => 'Data',
			// page name must end in ".tab", and contain at least one symbol
			'pattern' => '/.\.tab$/',
			'license' => 'CC-BY-SA 4.0',
			'isLocal' => false,
		],
	];

	if ( $wgDBname !== 'commonswiki'
	) {
		$wgJsonConfigs['Map.JsonConfig']['remote'] = [
			'url' => 'https://commons.wikishack.com/w/api.php'
		];

		$wgJsonConfigs['Tabular.JsonConfig']['remote'] = [
			'url' => 'https://commons.wikishack.com/w/api.php'
		];
	}
}

// Vector
$vectorVersion = $wgDefaultSkin === 'vector-2022' ? '2' : '1';
$wgVectorDefaultSkinVersionForExistingAccounts = $vectorVersion;

// Don't need a global here
unset( $vectorVersion );

// Licensing variables

$wikishackhost = $wi->isBeta() ? 'wikishack.dev' : 'wikishack.com';

/**
 * Default values.
 * We can not set these in LocalSettings.php, to prevent them
 * from causing absolute overrides.
 */
$wgRightsIcon = "https://meta.$wikishackhost/{$wi->version}/resources/assets/licenses/cc-by-sa.png";
$wgRightsText = 'Creative Commons Attribution Share Alike';
$wgRightsUrl = 'https://creativecommons.org/licenses/by-sa/4.0/';

/**
 * Override values from ManageWiki.
 * If set in LocalSettings.php, this will be overridden
 * by wiki values there, due to caching forcing SiteConfiguration
 * values to be absolute overrides. This is however how licensing should
 * be forced. LocalSettings.php values should take priority, which they do.
 */
switch ( $wmgWikiLicense ) {
	case 'arr':
		$wgRightsIcon = 'https://static.wikishack.com/commonswiki/6/67/License_icon-copyright-88x31.svg';
		$wgRightsText = 'All Rights Reserved';
		$wgRightsUrl = false;
		break;
	case 'cc-by':
		$wgRightsIcon = "https://meta.$wikishackhost/{$wi->version}/resources/assets/licenses/cc-by.png";
		$wgRightsText = 'Creative Commons Attribution 4.0 International (CC BY 4.0)';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by/4.0';
		break;
	case 'cc-by-nc':
		$wgRightsIcon = 'https://mirrors.creativecommons.org/presskit/buttons/88x31/png/by-nc.png';
		$wgRightsText = 'Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by-nc/4.0/';
		break;
	case 'cc-by-nd':
		$wgRightsIcon = 'https://mirrors.creativecommons.org/presskit/buttons/88x31/png/by-nd.png';
		$wgRightsText = 'Creative Commons Attribution-NoDerivatives 4.0 International (CC BY-ND 4.0)';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by-nd/4.0/';
		break;
	case 'cc-by-sa':
		$wgRightsText = 'Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by-sa/4.0/';
		break;
	case 'cc-by-sa-2-0-kr':
		$wgRightsText = 'Creative Commons BY-SA 2.0 Korea';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by-sa/2.0/kr';
		break;
	case 'cc-by-sa-nc':
		$wgRightsIcon = "https://meta.$wikishackhost/{$wi->version}/resources/assets/licenses/cc-by-nc-sa.png";
		$wgRightsText = 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by-nc-sa/4.0/';
		break;
	case 'cc-by-nc-nd':
		$wgRightsIcon = 'https://mirrors.creativecommons.org/presskit/buttons/88x31/png/by-nc-nd.png';
		$wgRightsText = 'Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)';
		$wgRightsUrl = 'https://creativecommons.org/licenses/by-nc-nd/4.0/';
		break;
	case 'cc-pd':
		$wgRightsIcon = "https://meta.$wikishackhost/{$wi->version}/resources/assets/licenses/cc-0.png";
		$wgRightsText = 'CC0 Public Domain';
		$wgRightsUrl = 'https://creativecommons.org/publicdomain/zero/1.0/';
		break;
	case 'gpl-v3':
		$wgRightsIcon = 'https://static.wikishack.com/commonswiki/d/d8/Gplv3-or-later.png';
		$wgRightsText = 'GPLv3';
		$wgRightsUrl = 'https://www.gnu.org/licenses/gpl-3.0-standalone.html';
		break;
	case 'gfdl':
		$wgRightsIcon = 'https://static.wikishack.com/commonswiki/6/61/Gfdl-logo-tiny.png';
		$wgRightsText = 'GNU Free Document License 1.3';
		$wgRightsUrl = 'https://www.gnu.org/licenses/fdl-1.3.en.html';
		break;
	case 'empty':
		break;
}

// Don't need a global here
unset( $wikishackhost );

