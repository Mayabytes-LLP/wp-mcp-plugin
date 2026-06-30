<?php

namespace WpMcp\Abilities;

use WpMcp\Docs;
use WpMcp\SchemaGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates registration of all plugin abilities with the WordPress Abilities API.
 *
 * Each ability group class is instantiated and its register() method called
 * in dependency order. The full list of ability slugs is collected for
 * server registration and tool filtering.
 */
class Registrar {

	private SchemaGenerator $schema_generator;

	/** @var string[] */
	private array $ability_names = array();

	/** @var string[] */
	private array $resource_names = array();

	/** @var string[] */
	private array $prompt_names = array();

	public function __construct( SchemaGenerator $schema_generator ) {
		$this->schema_generator = $schema_generator;
	}

	/**
	 * Register all ability groups.
	 */
	public function register_all(): void {
		$query    = new Query( $this->schema_generator );
		$page     = new Page();
		$element  = new Element();
		$settings = new Settings();
		$render   = new Render();
		$guide    = new Guide();

		$query->register();
		$page->register();
		$element->register();
		$settings->register();
		$render->register();
		$guide->register();

		$this->ability_names = array_merge(
			$query->get_ability_names(),
			$page->get_ability_names(),
			$element->get_ability_names(),
			$settings->get_ability_names(),
			$render->get_ability_names(),
			$guide->get_ability_names()
		);

		// Register MCP documentation resources.
		$docs = new Docs();
		$docs->register();
		$this->resource_names = $docs->get_resource_names();

		$this->prompt_names = $guide->get_prompt_names();
	}

	/**
	 * @return string[] All registered ability slugs.
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * @return string[] All registered resource ability slugs.
	 */
	public function get_resource_names(): array {
		return $this->resource_names;
	}

	/**
	 * @return string[] All registered prompt ability slugs.
	 */
	public function get_prompt_names(): array {
		return $this->prompt_names;
	}
}
