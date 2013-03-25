<?php

/**
 * Page for displaying diagnostics about the dispatch process.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class SpecialDispatchStats extends SpecialWikibasePage {

	/**
	 * Constructor.
	 *
	 * @since 0.2
	 */
	public function __construct() {
		parent::__construct( 'DispatchStats' );

	}

	protected function outputRow( $data, $tag = 'td', $attr = array() ) {
		$this->getOutput()->addHTML( Html::openElement( 'tr' ));

		foreach ( $data as $v ) {
			if ( !isset( $attr['align'] ) ) {
				if ( is_int( $v ) || is_float( $v ) ) {
					$attr['align'] = 'right';
				} else {
					$attr['align'] = 'right';
				}
			}

			$this->getOutput()->addHTML( Html::element( $tag, $attr, $v ));
		}

		$this->getOutput()->addHTML( Html::closeElement( 'tr' ));
	}

	protected function outputStateRow( $label, $state ) {
		$lang = $this->getContext()->getLanguage();

		$this->outputRow( array(
			$label,
			isset( $state->chd_site ) ? $state->chd_site : '',
			$lang->formatNum( $state->chd_dist ),
			$lang->formatDuration( $state->chd_lag ),
		) );
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$lang = $this->getContext()->getLanguage();

		$stats = new \Wikibase\DispatchStats();
		$stats->load();

		$this->getOutput()->addHTML( Html::rawElement( 'p', array(),
			$this->msg( 'wikibase-dispatchstats-intro' )->parse() ) );

		if ( !$stats->hasStats() ) {
			$this->getOutput()->addHTML( Html::rawElement( 'p', array(),
				$this->msg( 'wikibase-dispatchstats-no-stats' )->parse() ) );

			return;
		}

		// changes ------
		$this->getOutput()->addHTML( Html::rawElement( 'h2', array(), $this->msg( 'wikibase-dispatchstats-changes' )->parse() ));

		$this->getOutput()->addHTML( Html::openElement( 'table', array( 'class' => 'wikitable' ) ));

		$this->outputRow( array(
			'',
			$this->msg( 'wikibase-dispatchstats-change-id' )->text(),
			$this->msg( 'wikibase-dispatchstats-change-timestamp' )->text(),
		), 'th' );

		$this->outputRow( array(
			$this->msg( 'wikibase-dispatchstats-oldest-change' )->text(),
			$stats->getMinChangeId(),
			$lang->timeanddate( $stats->getMinChangeTimestamp() ),
		) );

		$this->outputRow( array(
			$this->msg( 'wikibase-dispatchstats-newest-change' )->text(),
			$stats->getMaxChangeId(),
			$lang->timeanddate( $stats->getMaxChangeTimestamp() ),
		) );

		$this->getOutput()->addHTML( Html::closeElement( 'table' ));

		// dispatch stats ------
		$this->getOutput()->addHTML( Html::rawElement( 'h2', array(), $this->msg( 'wikibase-dispatchstats-stats' )->parse() ));

		$this->getOutput()->addHTML( Html::openElement( 'table', array( 'class' => 'wikitable' ) ));

		$this->outputRow( array(
			'',
			$this->msg( 'wikibase-dispatchstats-site-id' )->text(),
			$this->msg( 'wikibase-dispatchstats-lag-num' )->text(),
			$this->msg( 'wikibase-dispatchstats-lag-time' )->text(),
		), 'th' );

		$this->outputStateRow(
			$this->msg( 'wikibase-dispatchstats-freshest' )->text(),
			$stats->getFreshest()
		);

		$this->outputStateRow(
			$this->msg( 'wikibase-dispatchstats-median' )->text(),
			$stats->getMedian()
		);

		$this->outputStateRow(
			$this->msg( 'wikibase-dispatchstats-stalest' )->text(),
			$stats->getStalest()
		);

		$this->outputStateRow(
			$this->msg( 'wikibase-dispatchstats-average' )->text(),
			$stats->getAverage()
		);

		$this->getOutput()->addHTML( Html::closeElement( 'table' ));
	}
}