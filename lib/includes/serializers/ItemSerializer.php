<?php
namespace Wikibase\Lib\Serializers;

use InvalidArgumentException;
use SiteSQLStore;
use SiteStore;
use Wikibase\Entity;
use Wikibase\EntityFactory;
use Wikibase\Item;

/**
 * Serializer for items.
 *
 * See docs/json.wiki for details of the format.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ItemSerializer extends EntitySerializer implements Unserializer {

	/**
	 * @since 0.4
	 *
	 * @var SiteStore
	 */
	protected $siteStore;

	/**
	 * Constructor.
	 *
	 * @since 0.4
	 *
	 * @param ClaimSerializer $claimSerializer
	 * @param SerializationOptions $options
	 * @param EntityFactory $entityFactory
	 * @param SiteStore $siteStore
	 *
	 * @todo: make $entityFactory required
	 * @todo: make $siteStore required (resp. inject a SiteLinkSerializer instead)
	 */
	public function __construct( ClaimSerializer $claimSerializer, SerializationOptions $options = null, EntityFactory $entityFactory = null, SiteStore $siteStore = null ) {
		if ( $siteStore === null ) {
			$this->siteStore = SiteSQLStore::newInstance();
		} else {
			$this->siteStore = $siteStore;
		}
		parent::__construct( $claimSerializer, $options, $entityFactory );
	}

	/**
	 * @see EntitySerializer::getEntityTypeSpecificSerialization
	 *
	 * @since 0.2
	 *
	 * @param Entity $item
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	protected function getEntityTypeSpecificSerialization( Entity $item ) {
		if ( !( $item instanceof Item ) ) {
			throw new InvalidArgumentException( 'ItemSerializer can only serialize Item implementing objects' );
		}

		//NOTE: when changing the serialization structure, update docs/json.wiki too!

		$serialization = array();

		$parts = $this->options->getOption( EntitySerializer::OPT_PARTS );

		if ( in_array( 'sitelinks', $parts ) ) {
			$siteLinkSerializer = new SiteLinkSerializer( $this->options, $this->siteStore );
			$siteLinks = $item->getSimpleSiteLinks();
			$serialization['sitelinks'] = $siteLinkSerializer->getSerialized( $siteLinks );
		}

		return $serialization;
	}

	/**
	 * @see Unserializer::newFromSerialization
	 *
	 * @since 0.5
	 *
	 * @param array $data
	 *
	 * @return Item
	 */
	public function newFromSerialization( array $data ) {
		/** @var Item $item */
		$item = parent::newFromSerialization( $data );

		if ( array_key_exists( 'sitelinks', $data ) ) {
			$siteLinkSerializer = new SiteLinkSerializer( $this->options, $this->siteStore );
			$siteLinks = $siteLinkSerializer->newFromSerialization( $data['sitelinks'] );

			foreach( $siteLinks as $siteLink ) {
				$item->addSimpleSiteLink( $siteLink );
			}
		}

		return $item;
	}
}
