<?php
/**
 * Plugin Name:       YAOTW Reading Time
 * Plugin URI:        https://github.com/yaotw/yaotw-reading-time
 * Description:       Ajoute un Dynamic Tag « Temps de lecture » à Elementor, plus un shortcode [yaotw_reading_time]. Must-use plugin, aucune activation nécessaire.
 * Version:           0.9.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            YAOTW
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yaotw-reading-time
 *
 * @package YAOTW_Reading_Time
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YAOTW_READING_TIME_VERSION', '0.9.1' );
define( 'YAOTW_READING_TIME_META_KEY', '_yaotw_reading_time_words' );

/**
 * Compte les mots d'un article, avec mise en cache en post meta.
 *
 * Le cache stocke le nombre de mots et non les minutes : le réglage
 * « mots par minute » peut ainsi être modifié dans l'éditeur Elementor
 * sans avoir à régénérer quoi que ce soit.
 *
 * @since 0.9.0
 *
 * @param int $post_id ID de l'article.
 * @return int Nombre de mots.
 */
function yaotw_reading_time_count_words( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return 0;
	}

	$cached = get_post_meta( $post_id, YAOTW_READING_TIME_META_KEY, true );

	if ( '' !== $cached ) {
		return absint( $cached );
	}

	$content = get_post_field( 'post_content', $post_id );

	/**
	 * Filtre le contenu analysé avant comptage.
	 *
	 * Point d'entrée pour les articles dont le texte ne vit pas dans
	 * post_content : champs ACF, contenu purement Elementor, CPT exotiques.
	 *
	 * @since 0.9.0
	 *
	 * @param string $content Contenu brut.
	 * @param int    $post_id ID de l'article.
	 */
	$content = apply_filters( 'yaotw_reading_time_content', $content, $post_id );

	$content = strip_shortcodes( (string) $content );
	$content = wp_strip_all_tags( $content );
	$content = trim( $content );

	if ( '' === $content ) {
		return 0;
	}

	$words = preg_split( '/[\s\x{00A0}]+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
	$count = is_array( $words ) ? count( $words ) : 0;

	update_post_meta( $post_id, YAOTW_READING_TIME_META_KEY, $count );

	return $count;
}

/**
 * Calcule le temps de lecture d'un article, en minutes.
 *
 * @since 0.9.0
 *
 * @param int|null $post_id ID de l'article. get_the_ID() par défaut.
 * @param int      $wpm     Mots par minute.
 * @return int Minutes, 0 si le contenu est vide.
 */
function yaotw_reading_time_get( $post_id = null, $wpm = 200 ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	$wpm     = max( 50, absint( $wpm ) );

	if ( ! $post_id ) {
		return 0;
	}

	$count = yaotw_reading_time_count_words( $post_id );

	if ( ! $count ) {
		return 0;
	}

	$minutes = (int) max( 1, ceil( $count / $wpm ) );

	/**
	 * Filtre le nombre de minutes retourné.
	 *
	 * @since 0.9.0
	 *
	 * @param int $minutes Minutes calculées.
	 * @param int $count   Nombre de mots.
	 * @param int $post_id ID de l'article.
	 * @param int $wpm     Mots par minute utilisés.
	 */
	return (int) apply_filters( 'yaotw_reading_time_minutes', $minutes, $count, $post_id, $wpm );
}

/**
 * Vide le cache du nombre de mots à la sauvegarde.
 *
 * @since 0.9.0
 *
 * @param int $post_id ID de l'article.
 * @return void
 */
function yaotw_reading_time_flush_cache( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	delete_post_meta( $post_id, YAOTW_READING_TIME_META_KEY );
}
add_action( 'save_post', 'yaotw_reading_time_flush_cache' );
add_action( 'elementor/editor/after_save', 'yaotw_reading_time_flush_cache' );

/**
 * Shortcode [yaotw_reading_time].
 *
 * Utile hors Elementor : RankMath, e-mails, widget texte, thème.
 *
 * @since 0.9.0
 *
 * @param array $atts Attributs du shortcode.
 * @return string
 */
function yaotw_reading_time_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'wpm'     => 200,
			'before'  => '',
			'after'   => ' min de lecture',
			'post_id' => 0,
		),
		$atts,
		'yaotw_reading_time'
	);

	$post_id = absint( $atts['post_id'] );
	$minutes = yaotw_reading_time_get( $post_id ? $post_id : null, absint( $atts['wpm'] ) );

	if ( ! $minutes ) {
		return '';
	}

	return sprintf(
		'<span class="yaotw-reading-time">%s</span>',
		esc_html( $atts['before'] . $minutes . $atts['after'] )
	);
}
add_shortcode( 'yaotw_reading_time', 'yaotw_reading_time_shortcode' );

/**
 * Déclare et enregistre le Dynamic Tag Elementor.
 *
 * La classe est déclarée dans le callback : un must-use plugin est chargé
 * bien avant Elementor, la classe parente n'existe pas encore au moment
 * où ce fichier est lu.
 *
 * @since 0.9.0
 *
 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Manager Elementor.
 * @return void
 */
function yaotw_reading_time_register_tag( $dynamic_tags_manager ) {

	if ( ! class_exists( 'YAOTW_Reading_Time_Tag' ) ) {

		/**
		 * Dynamic Tag « Temps de lecture ».
		 *
		 * @since 0.9.0
		 */
		class YAOTW_Reading_Time_Tag extends \Elementor\Core\DynamicTags\Tag {

			/**
			 * Nom interne du tag.
			 *
			 * @return string
			 */
			public function get_name() {
				return 'yaotw-reading-time';
			}

			/**
			 * Libellé affiché dans l'éditeur.
			 *
			 * @return string
			 */
			public function get_title() {
				return esc_html__( 'Temps de lecture', 'yaotw-reading-time' );
			}

			/**
			 * Groupe du tag.
			 *
			 * @return string
			 */
			public function get_group() {
				return 'yaotw';
			}

			/**
			 * Catégories de contrôles compatibles.
			 *
			 * @return array
			 */
			public function get_categories() {
				return array(
					\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
				);
			}

			/**
			 * Contrôles du tag.
			 *
			 * Ne déclarer ici ni « before », ni « after », ni « fallback » :
			 * ces IDs sont déjà pris par la section Avancé de
			 * \Elementor\Core\DynamicTags\Tag, qui les applique elle-même
			 * autour de la sortie de render(). Les redéclarer produit un
			 * doublon à l'affichage.
			 *
			 * @return void
			 */
			protected function register_controls() {
				$this->add_control(
					'wpm',
					array(
						'label'       => esc_html__( 'Mots par minute', 'yaotw-reading-time' ),
						'type'        => \Elementor\Controls_Manager::NUMBER,
						'default'     => 200,
						'min'         => 50,
						'max'         => 600,
						'step'        => 10,
						'description' => esc_html__( 'Moyenne de lecture en français : 200.', 'yaotw-reading-time' ),
					)
				);
			}

			/**
			 * Rendu du tag.
			 *
			 * Sort la valeur nue. Le préfixe, le suffixe et la valeur de repli
			 * sont gérés par la section Avancé native d'Elementor.
			 *
			 * @return void
			 */
			public function render() {
				$settings = $this->get_settings_for_display();

				$wpm     = isset( $settings['wpm'] ) ? absint( $settings['wpm'] ) : 200;
				$minutes = yaotw_reading_time_get( null, $wpm );

				if ( ! $minutes ) {
					return;
				}

				echo esc_html( $minutes );
			}
		}
	}

	$dynamic_tags_manager->register_group(
		'yaotw',
		array(
			'title' => esc_html__( 'YAOTW', 'yaotw-reading-time' ),
		)
	);

	$dynamic_tags_manager->register( new \YAOTW_Reading_Time_Tag() );
}
add_action( 'elementor/dynamic_tags/register', 'yaotw_reading_time_register_tag' );