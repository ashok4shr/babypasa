<?php
/**
 * Plugin Name: Babypasa SEO Enhancements
 * Description: Technical SEO schema and meta enhancements for babypasa.com.
 *              Supplements Rank Math free tier. Safe for future Rank Math Pro upgrade.
 * Version: 1.0.0
 * Author: Ashok Shrestha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Shared contact / social configuration
 *
 * All values are filterable so they can be tuned without editing this file.
 * URLs are NOT hard-coded to a domain — schema URLs come from home_url(),
 * so this plugin behaves identically on localhost and on production.
 * ---------------------------------------------------------------------- */

/**
 * Business contact details used in Organization schema.
 *
 * @return array
 */
function babypasa_seo_contact() {
	return apply_filters(
		'babypasa_seo_contact',
		array(
			'telephone' => '+977-9705511177',
			'email'     => 'sales@babypasa.com',
			'whatsapp'  => '9779705511177', // digits only, used to build wa.me link
		)
	);
}

/**
 * Social / external profile URLs for Organization "sameAs".
 *
 * @return array
 */
function babypasa_seo_social_profiles() {
	$contact = babypasa_seo_contact();

	$profiles = array(
		'https://www.facebook.com/BabyPasaNepal/',
		'https://www.instagram.com/babypasanepal',
	);

	if ( ! empty( $contact['whatsapp'] ) ) {
		$profiles[] = 'https://wa.me/' . preg_replace( '/\D/', '', $contact['whatsapp'] );
	}

	return apply_filters( 'babypasa_seo_social_profiles', $profiles );
}

/* -------------------------------------------------------------------------
 * Task B — Organization enrichment
 *
 * Rank Math already outputs an Organization (LocalBusiness) node and a
 * WebSite node with a SearchAction. We do NOT add new nodes (that would
 * duplicate). We merge contactPoint / telephone / email / sameAs into the
 * EXISTING organization node, matched by @id (#organization) or @type.
 * ---------------------------------------------------------------------- */
add_filter( 'rank_math/json_ld', 'babypasa_seo_enrich_organization', 99, 2 );

/**
 * @param array  $data   Rank Math schema entities (keyed map).
 * @param object $jsonld Rank Math JsonLD instance.
 * @return array
 */
function babypasa_seo_enrich_organization( $data, $jsonld ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$contact  = babypasa_seo_contact();
	$profiles = babypasa_seo_social_profiles();

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) || ! babypasa_seo_is_org_entity( $entity ) ) {
			continue;
		}

		// sameAs — merge without duplicating any value Rank Math may already set.
		$existing = isset( $entity['sameAs'] ) ? (array) $entity['sameAs'] : array();
		$entity['sameAs'] = array_values( array_unique( array_merge( $existing, $profiles ) ) );

		// Top-level contact fields (only if not already present).
		if ( empty( $entity['telephone'] ) && ! empty( $contact['telephone'] ) ) {
			$entity['telephone'] = $contact['telephone'];
		}
		if ( empty( $entity['email'] ) && ! empty( $contact['email'] ) ) {
			$entity['email'] = $contact['email'];
		}

		// contactPoint — customer service for Nepal.
		if ( empty( $entity['contactPoint'] ) ) {
			$entity['contactPoint'] = array(
				'@type'             => 'ContactPoint',
				'contactType'       => 'customer service',
				'telephone'         => $contact['telephone'],
				'email'             => $contact['email'],
				'areaServed'        => 'NP',
				'availableLanguage' => array( 'en', 'ne' ),
			);
		}

		$data[ $key ] = $entity;
	}

	return $data;
}

/**
 * Is the given schema entity the site Organization / LocalBusiness node?
 *
 * @param array $entity Schema entity.
 * @return bool
 */
function babypasa_seo_is_org_entity( $entity ) {
	if ( isset( $entity['@id'] ) && false !== strpos( (string) $entity['@id'], '#organization' ) ) {
		return true;
	}

	if ( ! isset( $entity['@type'] ) ) {
		return false;
	}

	$types = (array) $entity['@type'];

	foreach ( $types as $type ) {
		if ( 'Organization' === $type || 'LocalBusiness' === $type ) {
			return true;
		}
	}

	return false;
}

/* -------------------------------------------------------------------------
 * Task C — Product schema: add shippingDetails (Nepal domestic)
 *
 * priceValidUntil + seller are already emitted by Rank Math, so we do NOT
 * touch them. brand is intentionally skipped: the product_brand taxonomy
 * has no terms assigned (see audit) — there is nothing to emit. The code
 * below will start emitting brand automatically once brands are populated.
 * ---------------------------------------------------------------------- */
add_filter( 'rank_math/json_ld', 'babypasa_seo_product_shipping', 99, 2 );

/**
 * @param array  $data   Rank Math schema entities.
 * @param object $jsonld Rank Math JsonLD instance.
 * @return array
 */
function babypasa_seo_product_shipping( $data, $jsonld ) {
	if ( ! is_array( $data ) || ! function_exists( 'is_product' ) || ! is_singular( 'product' ) ) {
		return $data;
	}

	$shipping = babypasa_seo_shipping_details();
	if ( empty( $shipping ) ) {
		return $data;
	}

	$brand = babypasa_seo_product_brand();

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) || ! isset( $entity['@type'] ) ) {
			continue;
		}

		$types = (array) $entity['@type'];
		if ( ! in_array( 'Product', $types, true ) ) {
			continue;
		}

		// Attach shippingDetails to every Offer node within the product.
		if ( isset( $entity['offers'] ) ) {
			$entity['offers'] = babypasa_seo_attach_shipping( $entity['offers'], $shipping );
		}

		// Add brand only when the product actually has one and RM hasn't set it.
		if ( $brand && empty( $entity['brand'] ) ) {
			$entity['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$data[ $key ] = $entity;
	}

	return $data;
}

/**
 * Recursively attach shippingDetails to Offer / AggregateOffer structures.
 *
 * @param array $offers   Offer node (single, list, or AggregateOffer).
 * @param array $shipping OfferShippingDetails node.
 * @return array
 */
function babypasa_seo_attach_shipping( $offers, $shipping ) {
	// A single Offer.
	if ( isset( $offers['@type'] ) && 'Offer' === $offers['@type'] ) {
		if ( empty( $offers['shippingDetails'] ) ) {
			$offers['shippingDetails'] = $shipping;
		}
		return $offers;
	}

	// AggregateOffer wrapping nested offers.
	if ( isset( $offers['@type'] ) && 'AggregateOffer' === $offers['@type'] && isset( $offers['offers'] ) ) {
		$offers['offers'] = babypasa_seo_attach_shipping( $offers['offers'], $shipping );
		return $offers;
	}

	// A numeric list of Offer nodes.
	if ( is_array( $offers ) ) {
		foreach ( $offers as $i => $offer ) {
			if ( is_array( $offer ) ) {
				$offers[ $i ] = babypasa_seo_attach_shipping( $offer, $shipping );
			}
		}
	}

	return $offers;
}

/**
 * Build the OfferShippingDetails node for Nepal domestic delivery.
 *
 * shippingRate is dynamic (Upaya Cargo, by destination) so it is omitted by
 * default. Set a flat value via the `babypasa_seo_shipping_rate` filter to
 * include it (e.g. return 100 for a flat NPR 100 rate, or 0 for free).
 *
 * @return array
 */
function babypasa_seo_shipping_details() {
	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'NPR';

	$details = array(
		'@type'               => 'OfferShippingDetails',
		'shippingDestination' => array(
			'@type'          => 'DefinedRegion',
			'addressCountry' => 'NP',
		),
		'deliveryTime'        => array(
			'@type'        => 'ShippingDeliveryTime',
			'handlingTime' => array(
				'@type'    => 'QuantitativeValue',
				'minValue' => 0,
				'maxValue' => 1,
				'unitCode' => 'DAY',
			),
			'transitTime'  => array(
				'@type'    => 'QuantitativeValue',
				'minValue' => 1,
				'maxValue' => 3,
				'unitCode' => 'DAY',
			),
		),
	);

	$rate = apply_filters( 'babypasa_seo_shipping_rate', null );
	if ( null !== $rate && '' !== $rate ) {
		$details['shippingRate'] = array(
			'@type'    => 'MonetaryAmount',
			'value'    => (string) $rate,
			'currency' => $currency,
		);
	}

	return apply_filters( 'babypasa_seo_shipping_details', $details );
}

/**
 * Read the current product's brand name from the WooCommerce product_brand
 * taxonomy, if assigned. Returns '' when none (brand is then skipped).
 *
 * @return string
 */
function babypasa_seo_product_brand() {
	$id = get_queried_object_id();
	if ( ! $id || ! taxonomy_exists( 'product_brand' ) ) {
		return '';
	}

	$terms = get_the_terms( $id, 'product_brand' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	$first = reset( $terms );
	return isset( $first->name ) ? $first->name : '';
}

/* -------------------------------------------------------------------------
 * Task H — Magento → WooCommerce pattern redirects (301)
 *
 * No old-URL mapping export exists, so these are PATTERN-level redirects of
 * legacy Magento route families to the closest WooCommerce equivalent.
 * Implemented in PHP (not .htaccess) so they are portable across Apache and
 * Nginx and migrate with the plugin file. Gated on is_404() so real
 * WooCommerce pages (/cart/, /checkout/, /my-account/) are never touched and
 * cannot loop.
 * ---------------------------------------------------------------------- */
add_action( 'template_redirect', 'babypasa_seo_magento_redirects', 1 );

function babypasa_seo_magento_redirects() {
	if ( ! is_404() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	// Path of the current request, relative to the WP install root.
	$request = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$request = trim( (string) $request, '/' );

	$home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path && 0 === strpos( $request, $home_path ) ) {
		$request = trim( substr( $request, strlen( $home_path ) ), '/' );
	}

	if ( '' === $request ) {
		return;
	}

	// Special case: Magento catalog search -> WP search, preserving the query.
	if ( preg_match( '#^catalogsearch/result#i', $request ) && ! empty( $_GET['q'] ) ) {
		$term = sanitize_text_field( wp_unslash( $_GET['q'] ) );
		wp_safe_redirect( home_url( '/?s=' . rawurlencode( $term ) ), 301 );
		exit;
	}

	// Ordered most-specific-first. First match wins.
	$map = array(
		'#^catalog/product/view#i'   => '/shop/',
		'#^catalog/category/view#i'  => '/shop/',
		'#^catalogsearch#i'          => '/shop/',
		'#^catalog#i'                => '/shop/',
		'#^customer/account/login#i' => '/my-account/',
		'#^customer/account/create#i'=> '/my-account/',
		'#^customer#i'               => '/my-account/',
		'#^sales/order#i'            => '/my-account/orders/',
		'#^checkout/cart#i'          => '/cart/',
		'#^checkout/onepage#i'       => '/checkout/',
		'#^checkout#i'               => '/checkout/',
		'#^wishlist#i'               => '/my-account/',
	);

	$map = apply_filters( 'babypasa_seo_magento_redirect_map', $map );

	foreach ( $map as $pattern => $target ) {
		if ( preg_match( $pattern, $request ) ) {
			wp_safe_redirect( home_url( $target ), 301 );
			exit;
		}
	}
}
