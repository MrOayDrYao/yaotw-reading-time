# YAOTW Reading Time

A WordPress must-use plugin that adds a **"Reading time" Elementor Dynamic Tag**, plus a shortcode fallback. No dependencies, nothing stored in the options table.

## Installation

Drop `yaotw-reading-time.php` straight into `/wp-content/mu-plugins/`.

```
wp-content/
	mu-plugins/
		yaotw-reading-time.php
```

Must-use plugins are only loaded from the root of `mu-plugins`, never from a subdirectory. There is no activation step and no way to deactivate it from the admin: the file is live as soon as it is present.

Create the `mu-plugins` directory if it does not exist yet.

## Usage

### Dynamic Tag (Elementor Pro)

On any text field (Heading, Text Editor, Icon Box, Post Info and so on), click the database icon, then **YAOTW → Temps de lecture**.

Works inside a Single Post template and inside a Loop Item: the tag resolves the post currently being iterated.

Available controls:

| Control | Default | Purpose |
|---|---|---|
| Mots par minute | 200 | Reading speed used for the calculation |
| Avant | empty | Prefix, e.g. `Lecture : ` |
| Après | ` min de lecture` | Suffix |
| Valeur de repli | empty | Shown when the content is empty |

Labels are in French, matching the editor language of the sites this plugin was written for. Change the `esc_html__()` strings, or load a translation against the `yaotw-reading-time` text domain.

### Shortcode

```
[yaotw_reading_time]
[yaotw_reading_time wpm="220" before="Reading time: " after=" minutes"]
[yaotw_reading_time post_id="42"]
```

### In PHP

```php
$minutes = yaotw_reading_time_get();          // current post, 200 wpm
$minutes = yaotw_reading_time_get( 42, 220 ); // post 42, 220 wpm
```

## How caching works

The **word count** is stored in post meta (`_yaotw_reading_time_words`), not the number of minutes. That way the "words per minute" setting stays editable in Elementor without regenerating anything.

The meta is written the first time each post is read, so no migration script is needed. It is deleted on `save_post` and on `elementor/editor/after_save`.

## Filters

### `yaotw_reading_time_content`

Changes the text being analysed. Useful when the content does not live in `post_content` (ACF fields, Elementor-only content).

```php
add_filter( 'yaotw_reading_time_content', function( $content, $post_id ) {
	if ( '' === trim( $content ) ) {
		$content = get_post_meta( $post_id, '_elementor_data', true );
	}

	return $content;
}, 10, 2 );
```

### `yaotw_reading_time_minutes`

Adjusts the final result, for instance to add padding for image-heavy posts.

```php
add_filter( 'yaotw_reading_time_minutes', function( $minutes, $count, $post_id, $wpm ) {
	return $minutes;
}, 10, 4 );
```

## Known limitation

The word count reads `post_content`. For a post built with Elementor, that field holds the rendered HTML and the result is reliable. For fully dynamic content (ACF, widgets with no static text), `post_content` may be empty and the tag returns the fallback value. Use the `yaotw_reading_time_content` filter in that case.

## CSS

The shortcode outputs a `<span class="yaotw-reading-time">`. The Dynamic Tag outputs plain text, so style the widget that carries it.

```css
.yaotw-reading-time {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 0.875rem;
	opacity: 0.75;
}
```

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Elementor 3.5+ for the Dynamic Tag (the `elementor/dynamic_tags/register` hook replaces the older `register_tags`)
- Elementor Pro to select the tag in the UI

The shortcode and the PHP function work without Elementor.

## License

GPL-2.0-or-later