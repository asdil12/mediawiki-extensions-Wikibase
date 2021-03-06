<?php

namespace Wikibase;

use DataValues\DataValue;
use Wikibase\Lib\FormattingException;
use Wikibase\Lib\PropertyNotFoundException;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\SnakFormatter;

/**
 * Base class for generating the HTML for a Claim in Entity View.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 *
 * @author H. Snater < mediawiki at snater.com >
 * @author Daniel Werner
 * @author Pragunbhutani
 * @author Katie Filbert < aude.wiki@gmail.com>
 */
class ClaimHtmlGenerator {

	/**
	 * @since 0.4
	 *
	 * @var SnakFormatter
	 */
	protected $snakFormatter;

	/**
	 * Constructor.
	 *
	 * @param SnakFormatter $snakFormatter
	 */
	public function __construct( SnakFormatter $snakFormatter ) {
		$this->snakFormatter = $snakFormatter;
	}

	/**
	 * Returns the Html for the main Snak.
	 *
	 * @param DataValue $value
	 * @return string
	 */
	protected function getMainSnakHtml( $value ) {
		$mainSnakHtml = wfTemplate( 'wb-snak',
			'wb-mainsnak',
			'', // Link to property. NOTE: we don't display this ever (instead, we generate it on
				// Claim group level) If this was a public function, this should be generated
				// anyhow since important when displaying a Claim on its own.
			'', // type selector, JS only
			( $value === '' ) ? '&nbsp;' : $value
		);

		return $mainSnakHtml;
	}

	/**
	 * Builds and returns the HTML representing a single WikibaseEntity's claim.
	 *
	 * @since 0.4
	 *
	 * @param Claim $claim the claim to render
	 * @param null|string $editSectionHtml has the html for the edit section
	 *
	 * @return string
	 */
	public function getHtmlForClaim(
		Claim $claim,
		$editSectionHtml = null
	) {
		wfProfileIn( __METHOD__ );

		try {
			$snakValueHtml = $this->snakFormatter->formatSnak( $claim->getMainSnak() );
		} catch ( FormattingException $ex ) {
			$snakValueHtml = '?'; // XXX: perhaps show error message?
		} catch ( PropertyNotFoundException $ex ) {
			$snakValueHtml = '?'; // XXX: perhaps show error message?
		}

		$mainSnakHtml = $this->getMainSnakHtml( $snakValueHtml );

		$rankHtml = '';

		if( is_a( $claim, 'Wikibase\Statement' ) ) {
			$serializedRank = ClaimSerializer::serializeRank( $claim->getRank() );

			$rankHtml = wfTemplate( 'wb-rankselector',
				'wb-rankselector-' . $serializedRank,
				wfMessage( 'wikibase-statementview-rank-' . $serializedRank )->text()
			);
		}

		// @todo: Use 'wb-claim' or 'wb-statement' template accordingly
		// @todo: get rid of usage of global wfTemplate function
		$claimHtml = wfTemplate( 'wb-statement',
			'', // additional classes
			$rankHtml,
			$claim->getGuid(),
			$mainSnakHtml,
			'', // TODO: Qualifiers
			$editSectionHtml,
			'', // TODO: References heading
			'' // TODO: References
		);

		wfProfileOut( __METHOD__ );
		return $claimHtml;
	}
}
