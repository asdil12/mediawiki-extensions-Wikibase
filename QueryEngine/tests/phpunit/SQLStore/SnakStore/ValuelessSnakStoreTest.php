<?php

namespace Wikibase\Tests\Query\SQLStore\SnakStore;

use DataValues\StringValue;
use Wikibase\QueryEngine\SQLStore\SnakStore\ValuelessSnakStore;
use Wikibase\QueryEngine\SQLStore\SnakStore\ValueSnakRow;
use Wikibase\QueryEngine\SQLStore\SnakStore\ValuelessSnakRow;
use Wikibase\SnakRole;

/**
 * Unit tests for the Wikibase\QueryEngine\SQLStore\SnakStore\NoValueSnakStore class.
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
 * @file
 * @since 0.1
 *
 * @ingroup WikibaseQueryEngineTest
 *
 * @group Wikibase
 * @group WikibaseQueryEngine
 * @group WikibaseSnakStore
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class NoValueSnakStoreTest extends SnakStoreTest {

	protected function getInstance() {
		return new ValuelessSnakStore(
			$this->getMock( 'Wikibase\Database\QueryInterface' ),
			'snaks_of_doom'
		);
	}

	public function canStoreProvider() {
		$argLists = array();

		$argLists[] = array( new ValuelessSnakRow(
			ValuelessSnakRow::TYPE_NO_VALUE,
			1,
			1,
			SnakRole::QUALIFIER
		) );

		$argLists[] = array( new ValuelessSnakRow(
			ValuelessSnakRow::TYPE_NO_VALUE,
			1,
			1,
			SnakRole::MAIN_SNAK
		) );

		$argLists[] = array( new ValuelessSnakRow(
			ValuelessSnakRow::TYPE_SOME_VALUE,
			1,
			1,
			SnakRole::QUALIFIER
		) );

		$argLists[] = array( new ValuelessSnakRow(
			ValuelessSnakRow::TYPE_SOME_VALUE,
			1,
			1,
			SnakRole::MAIN_SNAK
		) );

		return $argLists;
	}

	public function cannotStoreProvider() {
		$argLists = array();

		$argLists[] = array( new ValueSnakRow(
			new StringValue( 'nyan' ),
			1,
			1,
			SnakRole::QUALIFIER,
			0
		) );

		$argLists[] = array( new ValueSnakRow(
			new StringValue( 'nyan' ),
			1,
			1,
			SnakRole::MAIN_SNAK,
			0
		) );

		return $argLists;
	}

	/**
	 * @dataProvider canStoreProvider
	 */
	public function testStoreSnak( ValuelessSnakRow $snakRow ) {
		$queryInterface = $this->getMock( 'Wikibase\Database\QueryInterface' );

		$queryInterface->expects( $this->once() )
			->method( 'insert' )
			->with(
				$this->equalTo( 'snaks_of_doom' ),
				$this->equalTo(
					array(
						'claim_id' => $snakRow->getInternalClaimId(),
						'property_id' => $snakRow->getInternalPropertyId(),
						'snak_type' => $snakRow->getInternalSnakType(),
						'snak_role' => $snakRow->getSnakRole(),
					)
				)
			);

		$store = new ValuelessSnakStore(
			$queryInterface,
			'snaks_of_doom'
		);

		$store->storeSnakRow( $snakRow );
	}

}