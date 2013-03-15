<?php
namespace Wikibase;

/**
 * File defining the hook handlers for the Wikibase Client extension.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseClient
 *
 * @licence GNU GPL v2+
 *
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Jens Ohlig
 * @author Daniel Kinzler
 * @author Tobias Gritschacher
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

final class ClientHooks {

	/**
	 * Schema update to set up the needed database tables.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @since 0.1
	 *
	 * @param \DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onSchemaUpdate( \DatabaseUpdater $updater ) {
		wfProfileIn( __METHOD__ );

		$type = $updater->getDB()->getType();

		if ( $type === 'mysql' || $type === 'sqlite' /* || $type === 'postgres' */ ) {
			$extension = $type === 'postgres' ? '.pg.sql' : '.sql';

			if ( Settings::get( 'repoDatabase' ) === null ) {
				// if we don't have direct access to the repo database, set up local caches.

				$updater->addExtensionTable(
					'wbc_entity_cache',
					__DIR__ . '/sql/WikibaseCache' . $extension
				);
			}

			// TODO: re-enable this once we are actually tracking item usage, etc
			/*
			$updater->addExtensionTable(
				'wbc_item_usage',
				__DIR__ . '/sql/WikibaseClient' . $extension
			);
			*/
		}
		else {
			wfWarn( "Database type '$type' is not supported by Wikibase Client." );
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.1
	 *
	 * @param array $files
	 *
	 * @return bool
	 */
	public static function registerUnitTests( array &$files ) {
		// @codeCoverageIgnoreStart
		$testFiles = array(
			'includes/LangLinkHandler',

			'includes/CachedEntity',
			'includes/ChangeHandler',
			'includes/ClientUtils',
			'includes/EntityCacheUpdater',

			'includes/api/ApiClientInfo',

			'includes/store/EntityCacheTable',
		);

		foreach ( $testFiles as $file ) {
			$files[] = __DIR__ . '/tests/phpunit/' . $file . 'Test.php';
		}

		return true;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Add new javascript testing modules. This is called after the addition of MediaWiki core test suites.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @since 0.4
	 *
	 * @param array &$testModules
	 * @param \ResourceLoader &$resourceLoader
	 *
	 * @return boolean
	 */
	 public static function onRegisterQUnitTests( array &$testModules, \ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['wikibase.client.watchlist.test'] = array(
			'scripts' => array(
				'tests/qunit/wbclient.watchlist.tests.js'
			),
			'dependencies' => array(
				'wbclient.watchlist',
				'wbclient.watchlist.css'
			),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikibase/client'
		);
		return true;
	}

	/**
	 * Deletes all the data stored on the repository.
	 *
	 * @since 0.2
	 *
	 * @param callable $reportMessage // takes a string param and echos it
	 *
	 * @return bool
	 */
	public static function onWikibaseDeleteData( $reportMessage ) {
		wfProfileIn( __METHOD__ );

		$store = ClientStoreFactory::getStore();
		$stores = array_flip( $GLOBALS['wgWBClientStores'] );

		$reportMessage( "Deleting data from the " . $stores[get_class( $store )] . " store..." );

		$store->clear();

		// @todo filter by something better than RC_EXTERNAL, in case something else uses that someday
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'recentchanges',
			array( 'rc_type' => RC_EXTERNAL ),
			__METHOD__
		);

		$reportMessage( "done!\n" );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Rebuilds all the data stored on the repository.
	 * This hook will probably be called manually when the
	 * rebuildAllData script is run on the client.
	 * @todo Be smarter and call this hook from pollForChanges
	 *
	 * @since 0.2
	 *
	 * @param callable $reportMessage // takes a string parameter and echos it
	 *
	 * @return bool
	 */
	public static function onWikibaseRebuildData( $reportMessage ) {
		wfProfileIn( __METHOD__ );

		$store = ClientStoreFactory::getStore();
		$stores = array_flip( $GLOBALS['wgWBClientStores'] );
		$reportMessage( "Rebuilding all data in the " . $stores[get_class( $store )] . " store on the client..." );
		$store->rebuild();
		$changes = ChangesTable::singleton();
		$changes = $changes->select(
			null,
			array(),
			array(),
			__METHOD__
		);
		ChangeHandler::singleton()->handleChanges( iterator_to_array( $changes ) );
		$reportMessage( "done!\n" );

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Hook for injecting a message on [[Special:MovePage]]
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialMovepageAfterMove
	 *
	 * @since 0.3
	 *
	 * @param \MovePageForm $movePage
	 * @param \Title &$oldTitle
	 * @param \Title &$newTitle
	 *
	 * @return bool
	 */
	public static function onSpecialMovepageAfterMove( \MovePageForm $movePage, \Title &$oldTitle, \Title &$newTitle ) {
		$siteLinkCache = ClientStoreFactory::getStore()->newSiteLinkTable();
		$globalId = Settings::get( 'siteGlobalID' );
		$itemId = $siteLinkCache->getItemIdForLink(
			$globalId,
			$oldTitle->getText()
		);

		if ( $itemId !== false ) {
			$itemByTitle = 'Special:ItemByTitle/' . $globalId . '/' . wfUrlencode( $oldTitle->getPrefixedDBkey() );
			$itemByTitleLink = ClientUtils::repoArticleUrl( $itemByTitle );
			$out = $movePage->getOutput();
			$out->addModules( 'wikibase.client.page-move' );
			$out->addHTML(
				\Html::rawElement(
					'div',
					array( 'id' => 'wbc-after-page-move',
							'class' => 'plainlinks' ),
					wfMessage( 'wikibase-after-page-move', $itemByTitleLink )->parse()
				)
			);
		}
		return true;
	}

	/**
	 * External library for Scribunto
	 *
	 * @since 0.4
	 *
	 * @param $engine
	 * @param array $extraLibraries
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries ( $engine, array &$extraLibraries ) {
		if ( Settings::get( 'allowDataTransclusion' ) === true ) {
			$extraLibraries['mw.wikibase'] = 'Scribunto_LuaWikibaseLibrary';
		}

		return true;
	}

	/**
	 * Hook for modifying the query for fetching recent changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialRecentChangesQuery
	 *
	 * @since 0.2
	 *
	 * @param &$conds[]
	 * @param &$tables[]
	 * @param &$join_conds[]
	 * @param \FormOptions $opts
	 * @param &$query_options[]
	 * @param &$fields[]
	 *
	 * @return bool
	 */
	public static function onSpecialRecentChangesQuery( array &$conds, array &$tables, array &$join_conds,
		\FormOptions $opts, array &$query_options, array &$fields ) {
		wfProfileIn( __METHOD__ );

		$rcFilterOpts = new RecentChangesFilterOptions( $opts );

		if ( $rcFilterOpts->showWikibaseEdits() === false ) {
			$conds[] = 'rc_type != ' . RC_EXTERNAL;
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Hook for formatting recent changes linkes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OldChangesListRecentChangesLine
	 *
	 * @since 0.2
	 *
	 * @param \ChangesList $changesList
	 * @param string $s
	 * @param \RecentChange $rc
	 *
	 * @return bool
	 */
	public static function onOldChangesListRecentChangesLine( \ChangesList &$changesList, &$s,
		\RecentChange $rc, &$classes = array() ) {

		wfProfileIn( __METHOD__ );

		$rcType = $rc->getAttribute( 'rc_type' );
		if ( $rcType == RC_EXTERNAL ) {
			$params = unserialize( $rc->getAttribute( 'rc_params' ) );

			if ( !is_array( $params ) ) {
				$varType = is_object( $params ) ? get_class( $params ) : gettype( $params );
				trigger_error( __CLASS__ . ' : $rc_params is not unserialized correctly.  It has '
					. 'been returned as ' . $varType, E_USER_WARNING );
				return false;
			}

			if ( array_key_exists( 'wikibase-repo-change', $params ) ) {
				$line = ExternalChangesLine::changesLine( $changesList, $rc );
				if ( $line == false ) {
					return false;
				}

				$classes[] = 'wikibase-edit';
				$s = $line;
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Modifies watchlist query to include external changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialWatchlistQuery
	 *
	 * @since 0.2
	 *
	 * @param array &$conds
	 * @param array &$tables
	 * @param array &$join_conds
	 * @param array &$fields
	 *
	 * @return bool
	 */
	public static function onSpecialWatchlistQuery( array &$conds, array &$tables, array &$join_conds, array &$fields ) {
		global $wgRequest, $wgUser;

		wfProfileIn( __METHOD__ );

		if ( $wgRequest->getBool( 'enhanced', $wgUser->getOption( 'usenewrc' ) ) === false ) {
			$dbr = wfGetDB( DB_SLAVE );

			$newConds = array();
			foreach( $conds as $k => $v ) {
				if ( $v ===  'rc_this_oldid=page_latest OR rc_type=3' ) {
					$where = array(
						'rc_this_oldid=page_latest',
						'rc_type' => array( 3, 5 )
					);
					$newConds[$k] = $dbr->makeList( $where, LIST_OR );
				} else {
					$newConds[$k] = $v;
				}
			}
			$conds = $newConds;
		} else {
			$conds[] = 'rc_type != 5';
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Hook runs after internal parsing
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 *
	 * @since 0.1
	 *
	 * @param \Parser $parser
	 * @param string $text
	 * @param \StripState $stripState
	 *
	 * @return bool
	 */
	public static function onParserAfterParse( \Parser &$parser, &$text, \StripState $stripState ) {
		wfProfileIn( __METHOD__ );

		$parserOutput = $parser->getOutput();

		// only run this once, for the article content and not interface stuff
		//FIXME: this also runs for messages in EditPage::showEditTools! Ugh!
		if ( $parser->getOptions()->getInterfaceMessage() ) {
			wfProfileOut( __METHOD__ );
			return true;
		}

		$langLinkHandler = new LangLinkHandler(
			Settings::get( 'siteGlobalID' ),
			Settings::get( 'namespaces' ),
			Settings::get( 'excludeNamespaces' ),
			ClientStoreFactory::getStore()->newSiteLinkTable(),
			\Sites::singleton() );

		$useRepoLinks = $langLinkHandler->useRepoLinks( $parser->getTitle(), $parser->getOutput() );

		if ( $useRepoLinks ) {
			// add links
			$langLinkHandler->addLinksFromRepository( $parser->getTitle(), $parser->getOutput() );
		}

		if ( $useRepoLinks || Settings::get( 'alwaysSort' ) ) {
			// sort links
			$interwikiSorter = new InterwikiSorter(
				Settings::get( 'sort' ),
				Settings::get( 'interwikiSortOrders' ),
				Settings::get( 'sortPrepend' )
			);
			$interwikiLinks = $parserOutput->getLanguageLinks();
			$sortedLinks = $interwikiSorter->sortLinks( $interwikiLinks );
			$parserOutput->setLanguageLinks( $sortedLinks );
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Adds css for the edit links sidebar link or JS to create a new item
	 * or to link with an existing one.
	 *
	 * @param \OutputPage &$out
	 * @param \Skin &$skin
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplay( \OutputPage &$out, \Skin &$skin ) {
		wfProfileIn( __METHOD__ );

		$title = $out->getTitle();
		$namespaceChecker = new NamespaceChecker(
			Settings::get( 'excludeNamespaces' ),
			Settings::get( 'namespaces' )
		);

		if ( $namespaceChecker->isWikibaseEnabled( $title->getNamespace() ) ) {
			$out->addModules( 'wikibase.client.init' );

			if ( !$out->getLanguageLinks() && \Action::getActionName( $skin->getContext() ) === 'view' && $title->exists() ) {
				// Module with the sole purpose to hide #p-lang
				// Needed as we can't do that in the regular CSS nor in JavaScript
				// (as that only runs after the element initially appeared).
				$out->addModules( 'wikibase.client.nolanglinks' );
				// Add the JavaScript to link pages locally
				$out->addModules( 'wbclient.linkItem' );
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Displays a list of links to pages on the central wiki at the end of the language box.
	 *
	 * @param \Skin $skin
	 * @param \QuickTemplate $template
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public static function onSkinTemplateOutputPageBeforeExec( \Skin &$skin, \QuickTemplate &$template ) {
		wfProfileIn( __METHOD__ );

		$title = $skin->getContext()->getTitle();
		if ( !in_array( $title->getNamespace(), Settings::get( 'excludeNamespaces' ) ) && $title->exists() ) {

			if ( empty( $template->data['language_urls'] ) && \Action::getActionName( $skin->getContext() ) === 'view' ) {
				// Placeholder in case the page doesn't have any langlinks yet
				// self::onBeforePageDisplay adds the JavaScript module which will overwrite this with a link
				$template->data['language_urls'][] = array(
					'text' => '',
					'id' => 'wbc-linkToItem',
					'class' => 'wbc-editpage wbc-nolanglinks',
				);

				wfProfileOut( __METHOD__ );
				return true;
			}

			$title = $skin->getContext()->getTitle();

			// gets the main part of the title, no underscores used in this db table
			// TODO: use the item id from the page props when they are available
			$titleText = $title->getPrefixedText();
			$siteId = Settings::get( 'siteGlobalID' );

			$itemId = ClientStoreFactory::getStore()->newSiteLinkTable()->getItemIdForLink( $siteId, $titleText );

			if ( $itemId ) {
				// links to the special page
				$template->data['language_urls'][] = array(
					'href' => ClientUtils::repoArticleUrl( "Special:ItemByTitle/$siteId/" . wfUrlencode( $title->getPrefixedDBkey() ) ),
					'text' => wfMessage( 'wikibase-editlinks' )->text(),
					'title' => wfMessage( 'wikibase-editlinkstitle' )->text(),
					'class' => 'wbc-editpage',
				);
			}
		}

		wfProfileOut( __METHOD__ );
		return true;
	}

	/**
	 * Adds a toggle for showing/hiding Wikidata entries in recent changes
	 *
	 * @param \SpecialRecentChanges $special
	 * @param array &$filters
	 *
	 * @return bool
	 */
	public static function onSpecialRecentChangesFilters( \SpecialRecentChanges $special, array &$filters ) {
		$context = $special->getContext();

		if ( $context->getRequest()->getBool( 'enhanced', $context->getUser()->getOption( 'usenewrc' ) ) === false ) {
			$showWikidata = $special->getUser()->getOption( 'rcshowwikidata' );
			$default = $showWikidata ? false : true;
			if ( $context->getUser()->getOption( 'usenewrc' ) === 0 ) {
				$filters['hidewikidata'] = array( 'msg' => 'wikibase-rc-hide-wikidata', 'default' => $default );
			}
		}

		return true;
	}

	/**
	 * Adds a preference for showing or hiding Wikidata entries in recent changes
	 *
	 * @param \User $user
	 * @param &$prefs[]
	 *
	 * @return bool
	 */
	public static function onGetPreferences( \User $user, array &$prefs ) {
		$prefs['rcshowwikidata'] = array(
			'type' => 'toggle',
			'label-message' => 'wikibase-rc-show-wikidata-pref',
			'section' => 'rc/advancedrc',
		);

		$prefs['wlshowwikibase'] = array(
			'type' => 'toggle',
			'label-message' => 'wikibase-watchlist-show-changes-pref',
			'section' => 'watchlist/advancedwatchlist',
		);

		return true;
	}

	/**
	 * Adds a JS stuff that provides a toggle for wikibase edits on the watchlist
	 *
	 * @param \SpecialPage $special
	 * @param string $subpage
	 *
	 * @return bool
	 */
	public static function onSpecialPageBeforeExecute( \SpecialPage $special, $subpage ) {
		if ( $special->getName() === 'Watchlist' ) {
			$context = $special->getContext();

			if ( $context->getRequest()->getBool( 'enhanced', $context->getUser()->getOption( 'usenewrc' ) ) === false ) {
				$special->getOutput()->addModules( array(
					'wbclient.watchlist.css',
					'wbclient.watchlist',
				) );
			}
		}

		return true;
	}

	/**
	 * Register the parser functions.
	 *
	 * @param $parser \Parser
	 *
	 * @return bool
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'noexternallanglinks', '\Wikibase\NoLangLinkHandler::handle', SFH_NO_HASH );

		if ( Settings::get( 'allowDataTransclusion' ) === true ) {
			$parser->setFunctionHook( 'property', array( '\Wikibase\PropertyParserFunction', 'render' ) );
		}

		return true;
	}

	/**
	 * Register the magic word.
	 */
	public static function onMagicWordwgVariableIDs( &$aCustomVariableIds ) {
		$aCustomVariableIds[] = 'noexternallanglinks';
		return true;
	}

	/**
	 * Apply the magic word.
	 */
	public static function onParserGetVariableValueSwitch( &$parser, &$cache, &$magicWordId, &$ret ) {
		if( $magicWordId == 'noexternallanglinks' ) {
			NoLangLinkHandler::handle( $parser, '*' );
		}

		return true;
	}

}
