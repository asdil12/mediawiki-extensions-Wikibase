<?php

namespace Wikibase\Repo\Specials;

use Html;
use InvalidArgumentException;
use Sites;
use Status;
use UserInputException;
use Wikibase\EntityContent;
use Wikibase\ChangeOp\ChangeOpSiteLink;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Summary;

/**
 * Special page for setting the sitepage of a Wikibase entity.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@googlemail.com >
 */
class SpecialSetSiteLink extends SpecialModifyEntity {

	/**
	 * The site of the site link.
	 *
	 * @since 0.4
	 *
	 * @var string
	 */
	protected $site;

	/**
	 * The page of the site link.
	 *
	 * @since 0.4
	 *
	 * @var string
	 */
	protected $page;

	/**
	 * Constructor
	 *
	 * @since 0.4
	 */
	public function __construct() {
		parent::__construct( 'SetSiteLink' );
	}

	/**
	 * @see SpecialModifyEntity::prepareArguments()
	 *
	 * @since 0.4
	 *
	 * @param string $subPage
	 */
	protected function prepareArguments( $subPage ) {
		parent::prepareArguments( $subPage );

		$request = $this->getRequest();
		// explode the sub page from the format Special:SetSitelink/q123/enwiki
		$parts = ( $subPage === '' ) ? array() : explode( '/', $subPage, 2 );

		// site
		$this->site = $request->getVal( 'site', isset( $parts[1] ) ? $parts[1] : '' );

		if ( $this->site === '' ) {
			$this->site = null;
		}

		if ( !$this->isValidSiteId( $this->site ) && $this->site !== null ) {
			$this->showErrorHTML( $this->msg( 'wikibase-setsitelink-invalid-site', $this->site )->parse() );
		}

		// title
		$this->page = $request->getVal( 'page' );
	}

	/**
	 * @see SpecialModifyEntity::parseEntityId()
	 */
	protected function parseEntityId( $rawId ) {
		try {
			return new ItemId( $rawId );
		} catch ( InvalidArgumentException $ex ) {
			throw new UserInputException(
				'wikibase-setsitelink-not-itemid',
				array( $rawId ),
				$ex->getMessage(),
				$ex
			);
		}
	}

	/**
	 * @see SpecialModifyEntity::modifyEntity()
	 *
	 * @since 0.4
	 *
	 * @return Summary|boolean The summary or false
	 */
	protected function modifyEntity() {
		// FIXME: This method is supposed to modify the entity and not alter the output. Do not
		// paste message directly into the HTML output in this method.

		$request = $this->getRequest();
		// has to be checked before modifying but is no error
		if ( $this->entityContent === null || !$this->isValidSiteId( $this->site ) || !$request->wasPosted() ) {
			$this->showCopyrightMessage();

			return false;
		}

		// to provide removing after posting the full form
		if ( $request->getVal( 'remove' ) === null && $this->page === '' ) {
			$this->showErrorHTML(
				$this->msg(
					'wikibase-setsitelink-warning-remove',
					$this->entityContent->getTitle()->getText()
				)->parse(),
				'warning'
			);
			return false;
		}

		try {
			$status = $this->setSiteLink( $this->entityContent, $this->site, $this->page, $summary );
		} catch ( ChangeOpException $e ) {
			$this->showErrorHTML( $e->getMessage() );
			return false;
		}

		if ( !$status->isGood() ) {
			$this->showErrorHTML( $status->getHTML() );
			return false;
		}

		return $summary;
	}

	/**
	 * Checks if the site id is valid.
	 *
	 * @since 0.4
	 *
	 * @param $siteId string the site id
	 *
	 * @return bool
	 */
	private function isValidSiteId( $siteId ) {
		return $siteId !== null && Sites::singleton()->getSite( $siteId ) !== null;
	}

	/**
	 * @see SpecialModifyEntity::getFormElements()
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	protected function getFormElements() {
		if ( $this->page === null ) {
			$this->page = $this->getSiteLink( $this->entityContent, $this->site );
		}
		$pageinput = Html::input(
			'page',
			$this->getRequest()->getVal( 'page' ),
			'text',
			array(
				'class' => 'wb-input wb-input-text',
				'id' => 'wb-setsitelink-page',
				'size' => 50
			)
		)
		. Html::element( 'br' );

		$site = \Sites::singleton()->getSite( $this->site );

		if ( $this->entityContent !== null && $this->site !== null && $site !== null ) {
			return Html::rawElement(
				'p',
				array(),
				$this->msg(
					'wikibase-setsitelink-introfull',
					$this->entityContent->getTitle()->getPrefixedText(),
					'[' . $site->getPageUrl( '' ) . ' ' . $this->site . ']'
				)->parse()
			)
			. Html::input( 'site', $this->site, 'hidden' )
			. Html::input( 'id', $this->entityContent->getTitle()->getText(), 'hidden' )
			. Html::input( 'remove', 'remove', 'hidden' )
			. $pageinput;
		}
		else {
			return Html::element(
				'p',
				array(),
				$this->msg( 'wikibase-setsitelink-intro' )->text()
			)
			. parent::getFormElements()
			. Html::element(
				'label',
				array(
					'for' => 'wb-setsitelink-site',
					'class' => 'wb-label'
				),
				$this->msg( 'wikibase-setsitelink-site' )->text()
			)
			. Html::input(
				'site',
				$this->getRequest()->getVal( 'site' ),
				'text',
				array(
					'class' => 'wb-input',
					'id' => 'wb-setsitelink-site'
				)
			)
			. Html::element( 'br' )
			. Html::element(
				'label',
				array(
					'for' => 'wb-setsitelink-page',
					'class' => 'wb-label'
				),
				$this->msg( 'wikibase-setsitelink-label' )->text()
			)
			. $pageinput;
		}
	}

	/**
	 * Returning the site page of the entity.
	 *
	 * @since 0.4
	 *
	 * @param EntityContent $entityContent
	 * @param string $siteId
	 *
	 * @return string
	 */
	protected function getSiteLink( $entityContent, $siteId ) {
		// FIXME: either the documentation here is wrong, or this check is not needed
		if ( $entityContent === null ) {
			return '';
		}

		if ( $entityContent->getEntity()->hasLinkToSite( $siteId ) ) {
			return $entityContent->getEntity()->getSimpleSitelink( $siteId )->getPageName();
		}

		return '';
	}

	/**
	 * Setting the sitepage of the entity.
	 *
	 * @since 0.4
	 *
	 * @param EntityContent $entityContent
	 * @param string $siteId
	 * @param string $pageName
	 * @param Summary &$summary The summary for this edit will be saved here.
	 *
	 * @return Status
	 */
	protected function setSiteLink( EntityContent $entityContent, $siteId, $pageName, &$summary ) {
		$status = Status::newGood();
		$site = Sites::singleton()->getSite( $siteId );

		if ( $site === null ) {
			$status->fatal( 'wikibase-setsitelink-invalid-site', $siteId );
			return $status;
		}

		$item = $entityContent->getItem();
		$summary = $this->getSummary( 'wbsetsitelink' );

		if ( $pageName === '' ) {
			$pageName = null;

			if ( !$item->hasLinkToSite( $siteId ) ) {
				$status->fatal( 'wikibase-setsitelink-remove-failed' );
				return $status;
			}
		} else {
			$pageName = $site->normalizePageName( $pageName );

			if ( $pageName === false ) {
				$status->fatal( 'wikibase-error-ui-no-external-page' );
				return $status;
			}
		}

		$changeOp = new ChangeOpSiteLink( $siteId, $pageName );

		$changeOp->apply( $item, $summary );

		return $status;
	}
}
