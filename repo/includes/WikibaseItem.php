<?php

/**
 * Represents a single Wikibase item.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Items
 *
 * @since 0.1
 *
 * @file WikibaseItem.php
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class WikibaseItem extends WikibaseEntity {

	/**
	 * @since 0.1
	 * @var array
	 */
	protected $data;

	/**
	 * Id of the item (the 42 in q42 used as page name and in exports).
	 * Integer when set. False when not initialized. Null when the item is new and unsaved.
	 *
	 * @since 0.1
	 * @var integer|false|null
	 */
	protected $id = false;

	/**
	 * @since 0.1
	 * @var WikiPage|false
	 */
	protected $wikiPage = false;

	/**
	 * Constructor.
	 * Do not use to construct new stuff from outside of this class, use the static newFoobar methods.
	 * In other words: treat as protected (which it was, but now cannot be since we derive from Content).
	 *
	 * @since 0.1
	 *
	 * @param array $data
	 */
	public function __construct( array $data ) {
		parent::__construct();

		$this->data = $data;
	}

	/**
	 * Cleans the internal array structure.
	 * This consists of adding elements the code expects to be present later on
	 * and migrating or removing elements after changes to the structure are made.
	 * Should typically be called before using any of the other methods.
	 *
	 * @since 0.1
	 */
	public function cleanStructure() {
		foreach ( array( 'links', 'label', 'description' ) as $field ) {
			if ( !array_key_exists( $field, $this->data ) ) {
				$this->data[$field] = array();
			}
		}
	}

	/**
	 * Get an array representing the WikibaseItem as they are
	 * stored in the article table and can be passed to newFromArray.
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public function toArray() {
		$data = $this->data;

		if ( is_null( $this->getId() ) ) {
			if ( array_key_exists( 'entity', $data ) ) {
				unset( $data['entity'] );
			}
		}
		else {
			$data['entity'] = 'q' . $this->getId();
		}

		return $data;
	}

	/**
	 * Returns the id of the item or null if it is not in the datastore yet.
	 *
	 * @since 0.1
	 *
	 * @return integer|null
	 */
	public function getId() {
		if ( $this->id === false ) {
			$this->id = array_key_exists( 'entity', $this->data ) ? (int)substr( $this->data['entity'], 1 ) : null;
		}

		return $this->id;
	}

	/**
	 * Saves the primary fields in the wb_items table.
	 * If the item does not exist yet (ie the id is null), it will be inserted, and the id will be set.
	 *
	 * @since 0.1
	 *
	 * @param integer $articleId
	 *
	 * @return boolean Success indicator
	 */
	public function save( /* $articleId */ ) {
		$dbw = wfGetDB( DB_MASTER );

		$fields = array();

		$success = true;

		if ( !$this->hasId() ) {
			$fields['item_id'] = null; // This is needed to have at least one field.
			//$fields['item_page_id'] = $articleId;

			$success = $dbw->insert(
				'wb_items',
				$fields,
				__METHOD__
			);

			if ( $success ) {
				$this->setId( $dbw->insertId() );
			}
		}
		elseif ( !empty( $fields ) ) {
			$success = $dbw->update(
				'wb_items',
				$fields,
				array( 'item_id' => $this->getId() ),
				__METHOD__
			);
		}

		return $success;
	}

	/**
	 * Sets the ID.
	 * Should only be set to something determined by the store and not by the user (to avoid duplicate IDs).
	 *
	 * @since 0.1
	 *
	 * @param integer $id
	 */
	protected function setId( $id ) {
		$this->id = $id;
	}

	/**
	 * Returns if the item has an ID set or not.
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function hasId() {
		return !is_null( $this->getId() );
	}

	/**
	 * Get the item id for a site and page pair.
	 * Returns false when there is no such pair.
	 *
	 * @since 0.1
	 *
	 * @param integer $siteId
	 * @param string $pageName
	 *
	 * @return false|integer
	 */
	public static function getIdForSiteLink( $siteId, $pageName ) {
		$dbr = wfGetDB( DB_SLAVE );

		$result = $dbr->selectRow(
			'wb_items_per_site',
			array( 'ips_item_id' ),
			array(
				'ips_site_id' => $siteId,
				'ips_site_page' => $pageName,
			),
			__METHOD__
		);

		return $result === false ? $result : $result->ips_item_id;
	}

	/**
	 * Get the ids of the items corresponding to the provided language and label pair.
	 * A description can also be provided, in which case only the id of the item with
	 * that description will be returned (as only element in the array).
	 *
	 * @since 0.1
	 *
	 * @param string $language
	 * @param string $label
	 * @param string|null $description
	 *
	 * @return array of integer
	 */
	public static function getIdsForLabel( $language, $label, $description = null ) {
		$dbr = wfGetDB( DB_SLAVE );

		$conds = array(
			'tpl_language' => $language,
			'tpl_label' => $label
		);

		if ( !is_null( $description ) ) {
			$conds['tpl_description'] = $description;
		}

		$items = $dbr->selectRow(
			'wb_texts_per_lang',
			array( 'ips_item_id' ),
			$conds,
			__METHOD__
		);

		return array_map( function( $item ) { return $item->ips_item_id; }, iterator_to_array( $items ) );
	}

	/**
	 * Sets the value for the label in a certain value.
	 *
	 * @since 0.1
	 *
	 * @param string $langCode
	 * @param string $value
	 */
	public function setLabel( $langCode, $value ) {
		$this->data['label'][$langCode] = array(
			'language' => $langCode,
			'value' => $value,
		);
	}

	/**
	 * Sets the value for the description in a certain value.
	 *
	 * @since 0.1
	 *
	 * @param string $langCode
	 * @param string $value
	 */
	public function setDescription( $langCode, $value ) {
		$this->data['description'][$langCode] = array(
			'language' => $langCode,
			'value' => $value,
		);
	}

	/**
	 * Remove label for a specific language in an item
	 * 
	 * @since 0.1
	 * 
	 * @param string|array $languages note that an empty array removes labels for no languages while a null pointer removes all
	 */
	public function removeLabel( $languages = array() ) {
		$this->removeMultilangTexts( 'label', (array)$languages );
	}

	/**
	 * Remove descriptions for a specific language in an item
	 * 
	 * @since 0.1
	 * 
	 * @param string|array $languages note that an empty array removes descriptions for no languages while a null pointer removes all
	 */
	public function removeDescription( $languages = array() ) {
		$this->removeMultilangTexts( 'description', (array)$languages );
	}

	/**
	 * Remove the value with a field specifier
	 *
	 * @since 0.1
	 *
	 * @param string $fieldKey
	 * @param array|null $languages
	 */
	protected function removeMultilangTexts( $fieldKey, array $languages = null ) {
		if ( !is_null( $languages ) ) {
			unset( $this->data[$fieldKey] );
			$this->data[$fieldKey] = array();
		}
		else {
			foreach ( $languages as $lang ) {
				unset( $this->data[$fieldKey][$lang] );
			}
		}
	}

	/**
	 * Get descriptions for an item
	 * 
	 * @since 0.1
	 * 
	 * @param array|null $languages note that an empty array gives descriptions for no languages whil a null pointer gives all
	 * 
	 * @return array found descriptions in given languages
	 */
	public function getDescriptions( array $languages = null ) {
		return $this->getMultilangTexts( 'description', $languages );
	}

	/**
	 * @since 0.1
	 * 
	 * Get labels for an item
	 * 
	 * @param array|null $languages note that an empty array gives labels for no languages while a null pointer gives all
	 * 
	 * @return array found labels in given languages
	 */
	public function getLabels( array $languages = null ) {
		return $this->getMultilangTexts( 'label', $languages );
	}

	/**
	 * @since 0.1
	 * 
	 * Get texts from an item with a field specifier
	 *
	 * @param string $fieldKey
	 * @param array|null $languages
	 *
	 * @return array
	 */
	protected function getMultilangTexts( $fieldKey, array $languages = null ) {
		$textList = $this->data[$fieldKey];

		if ( !is_null( $languages ) ) {
			$textList = array_filter( $textList, function( $textData ) use ( $languages ) {
				return in_array( $textData['language'], $languages );
			} );
		}

		$texts = array();

		foreach ( $textList as $languageCode => $textData ) {
			$texts[$languageCode] = $textData['value'];
		}

		return $texts;
	}

	/**
	 * Adds a site link.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $pageName
	 * @param string $updateType
	 *
	 * @return boolean Success indicator
	 */
	public function addSiteLink( $siteId, $pageName, $updateType = 'set' ) {
		/*
		if ( !array_key_exists( $siteId, $this->data['links'] ) ) {
			$this->data['links'][$siteId] = array();
		}
		*/

		/*
		$success =
			!( ( $updateType === 'add' && array_key_exists( $siteId, $this->data['links'][$siteId] ) )
			|| ( $updateType === 'update' && !array_key_exists( $siteId, $this->data['links'][$siteId] ) ) );
			*/
		$success =
			( $updateType === 'add' && !array_key_exists( $siteId, $this->data['links'] ) )
			|| ( $updateType === 'update' && array_key_exists( $siteId, $this->data['links'] ) )
			|| ( $updateType === 'set' );
			
		if ( $success ) {
			$this->data['links'][$siteId] = array(
				'site' => $siteId,
				'title' => $pageName
			);
		}

		return $success;

		// TODO: verify the link is allowed (ie no other item already links here)

//		$dbw = wfGetDB( DB_MASTER );
//
//		if ( $update ) {
//			$this->removeLInkSite( $siteId, $pageName );
//		}
//		else {
//			$exists = $dbw->selectRow(
//				'wb_items_per_site',
//				array( 'ips_item_id' ),
//				$this->getLinkSiteConds( $siteId, $pageName ),
//				__METHOD__
//			) !== false;
//
//			if ( $exists ) {
//				return false;
//			}
//		}
//
//		return $dbw->insert(
//			'wb_items_per_site',
//			$this->getLinkSiteConds( $siteId, $pageName ),
//			__METHOD__
//		);
	}

	/**
	 * Returns the conditions needed to find the link to an external page
	 * for this item.
	 *
	 * @since 0.1
	 *
	 * @param integer $siteId
	 * @param string $pageName
	 *
	 * @return array
	 */
	protected function getLinkSiteConds( $siteId, $pageName ) {
		return array(
			'ips_item_id' => $this->getId(),
			'ips_site_id' => $siteId,
			'ips_site_page' => $pageName,
		);
	}

	/**
	 * Removes a site link.
	 *
	 * @since 0.1
	 *
	 * @param integer $siteId
	 * @param string $pageName
	 *
	 * @return boolean Success indicator
	 */
	public function removeLinkSite( $siteId, $pageName ) {
		$success = array_key_exists( $siteId, $this->data['links'] ) && $this->data['links'][$siteId]['title'] === $pageName;

		if ( $success ) {
			unset( $this->data['links'][$siteId] );
		}

		return $success;

//		$dbw = wfGetDB( DB_MASTER );
//
//		return $dbw->delete(
//			'wb_items_per_site',
//			$this->getLinkSiteConds( $siteId, $pageName ),
//			__METHOD__
//		);
	}

	public function getPropertyNames() {
		//TODO: implement
	}

	public function getSystemPropertyNames() {
		//TODO: implement
	}

	public function getEditorialPropertyNames() {
		//TODO: implement
	}

	public function getStatementPropertyNames() {
		//TODO: implement
	}

	public function getPropertyMultilang( $name, $languages = null ) {
		//TODO: implement
	}

	public function getProperty( $name, $lang = null ) {
		//TODO: implement
	}

	public function getPropertyType( $name ) {
		//TODO: implement
	}

	public function isStatementProperty( $name ) {
		//TODO: implement
	}

	/**
	 * Returns the description of the item in the language with the provided code,
	 * or false in cases there is none in this language.
	 *
	 * @since 0.1
	 *
	 * @param string $langCode
	 *
	 * @return string|false
	 */
	public function getDescription( $langCode ) {
		return array_key_exists( $langCode, $this->data['description'] )
			? $this->data['description'][$langCode]['value'] : false;
	}

	/**
	 * Returns the label of the item in the language with the provided code,
	 * or false in cases there is none in this language.
	 *
	 * @since 0.1
	 *
	 * @param string $langCode
	 *
	 * @return string|false
	 */
	public function getLabel( $langCode ) {
		return array_key_exists( $langCode, $this->data['label'] )
			? $this->data['label'][$langCode]['value'] : false;
	}

	/**
	 * Returns the site links in an associative array with the following format:
	 * site id (str) => page title (str)
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public function getSiteLinks() {
		$titles = array();

		foreach ( $this->data['links'] as $siteId => $linkData ) {
			$titles[$siteId] = $linkData['title'];
		}

		return $titles;
	}

	/**
	 * @return String a string representing the content in a way useful for building a full text search index.
	 *		 If no useful representation exists, this method returns an empty string.
	 */
	public function getTextForSearchIndex() {
		return ''; #TODO: recursively collect all values from all properties.
	}

	/**
	 * @return String the wikitext to include when another page includes this  content, or false if the content is not
	 *		 includable in a wikitext page.
	 */
	public function getWikitextForTransclusion() {
		return false;
	}

	/**
	 * Returns a textual representation of the content suitable for use in edit summaries and log messages.
	 *
	 * @param int $maxlength maximum length of the summary text
	 * @return String the summary text
	 */
	public function getTextForSummary( $maxlength = 250 ) {
		return $this->getDescription( $GLOBALS['wgLang'] );
	}

	/**
	 * Returns native represenation of the data. Interpretation depends on the data model used,
	 * as given by getDataModel().
	 *
	 * @return mixed the native representation of the content. Could be a string, a nested array
	 *		 structure, an object, a binary blob... anything, really.
	 */
	public function getNativeData() {
		return $this->toArray();
	}

	/**
	 * returns the content's nominal size in bogo-bytes.
	 *
	 * @return int
	 */
	public function getSize()  {
		return strlen( serialize( $this->toArray() ) ); #TODO: keep and reuse value, content object is immutable!
	}

	/**
	 * Returns true if this content is countable as a "real" wiki page, provided
	 * that it's also in a countable location (e.g. a current revision in the main namespace).
	 *
	 * @param boolean $hasLinks: if it is known whether this content contains links, provide this information here,
	 *						to avoid redundant parsing to find out.
	 * @return boolean
	 */
	public function isCountable( $hasLinks = null ) {
		// TODO: implement
		return false;
		//return !empty( $this->data[ WikibaseContent::PROP_DESCRIPTION ] ); #TODO: better/more methods
	}

	/**
	 * @return boolean
	 */
	public function isEmpty()  {
		// TODO: might want to have better handling for this.
		// What does it mean for an item to be empty?
		// Certainly not the current check as it can have elements that are empty arrays, making base array non-empty.
		$data = $this->toArray();
		return empty( $data );
	}


	/**
	 * Returns a ParserOutput object containing the HTML.
	 *
	 * @since 0.1
	 *
	 * @param IContextSource $context
	 * @param null $revId
	 * @param null|ParserOptions $options
	 * @param bool $generateHtml
	 *
	 * @return ParserOutput
	 */
	public function getParserOutput( IContextSource $context, $revId = null, ParserOptions $options = null, $generateHtml = true )  {
		$itemView = new WikibaseItemView( $this, $context );
		$parserOutput = new ParserOutput( $itemView->getHTML() );

		$parserOutput->addSecondaryDataUpdate( new WikibaseItemStructuredSave( $this, $context->getTitle() ) );

		return $parserOutput;
	}

	/**
	 * @since 0.1
	 * @see Content::copy
	 * @return WikibaseItem
	 */
	public function copy() {
		$array = array();

		foreach ( $this->toArray() as $key => $value ) {
			$array[$key] = is_object( $value ) ? clone $value : $value;
		}

		return new self( $array );
	}

	/**
	 * Returns the WikiPage for the item or false if there is none.
	 *
	 * @since 0.1
	 *
	 * @return WikiPage|false
	 */
	public function getWikiPage() {
		if ( $this->wikiPage === false ) {
			$this->wikiPage = $this->hasId() ? self::getWikiPageForId( $this->getId() ) : false;
		}

		return $this->wikiPage;
	}

	/**
	 * Returns the Title for the item or false if there is none.
	 *
	 * @since 0.1
	 *
	 * @return Title|false
	 */
	public function getTitle() {
		$wikiPage = $this->getWikiPage();
		return $wikiPage === false ? false : $wikiPage->getTitle();
	}

	/**
	 * Get the item with the provided id, or null if there is no such item.
	 *
	 * @since 0.1
	 *
	 * @param integer $itemId
	 *
	 * @return Content|null
	 */
	public static function getFromId( $itemId ) {
		// TODO: since we already did the trouble of getting a WikiPage here,
		// we probably want to keep a copy of it in the Content object.
		return self::getWikiPageForId( $itemId )->getContent();
	}

	/**
	 * Get the item corresponding to the provided site and title pair, or null if there is no such item.
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $pageName
	 *
	 * @return Content|null
	 */
	public static function getFromSiteLink( $siteId, $pageName ) {
		$id = self::getIdForSiteLink( $siteId, $pageName );
		return $id === false ? null : self::getFromId( $id );
	}

	/**
	 * Get the items corresponding to the provided language and label pair.
	 * A description can also be provided, in which case only the item with
	 * that description will be returned (as only element in the array).
	 *
	 * @since 0.1
	 *
	 * @param string $language
	 * @param string $label
	 * @param string|null $description
	 *
	 * @return array of WikibaseItem
	 */
	public static function getFromLabel( $language, $label, $description = null ) {
		$ids = self::getIdsForLabel( $language, $label, $description );
		$items = array();

		foreach ( $ids as $id ) {
			$item = self::getFromId( $id );

			if ( !is_null( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Returns the WikiPage object for the item with provided id.
	 *
	 * @since 0.1
	 *
	 * @param integer $itemId
	 *
	 * @return WikiPage
	 */
	public static function getWikiPageForId( $itemId ) {
		return new WikiPage( self::getTitleForId( $itemId ) );
	}

	/**
	 * Returns the Title object for the item with provided id.
	 *
	 * @since 0.1
	 *
	 * @param integer $itemId
	 *
	 * @return Title
	 */
	public static function getTitleForId( $itemId ) {
		return Title::newFromText( 'Data:Q' . $itemId ); // TODO
	}

	/**
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return WikibaseItem
	 */
	public static function newFromArray( array $data ) {
		$item = new static( $data, true );
		$item->cleanStructure();
		return $item;
	}

	/**
	 * @since 0.1
	 *
	 * @return WikibaseItem
	 */
	public static function newEmpty() {
		return self::newFromArray( array() );
	}

}
