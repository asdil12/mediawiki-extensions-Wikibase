<?php

namespace Wikibase;

/**
 * Interface for objects representing changes.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

interface Change {

	/**
	 * Returns the user that made the change.
	 *
	 * @since 0.1
	 *
	 * @return \User
	 */
	public function getUser();

	/**
	 * Returns the age of the change in seconds.
	 *
	 * @since 0.1
	 *
	 * @return integer
	 */
	public function getAge();

	/**
	 * Returns whether the change is empty.
	 * If it's empty, it can be ignored.
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function isEmpty();

	/**
	 * Returns the type of change.
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getType();

	/**
	 * Returns the time on which the change was made as a timestamp in TS_MW format.
	 *
	 * @since 0.2
	 *
	 * @return string TS_MW
	 */
	public function getTime();

	/**
	 * Returns the id of the change.
	 *
	 * @since 0.2
	 *
	 * @return integer
	 */
	public function getId();

	/**
	 * Returns the id of the affected object (ie item or property).
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	public function getObjectId();

}
