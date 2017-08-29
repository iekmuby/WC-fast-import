<?php
////Stat
//Inserts 8
//Selects 10

function err($data, $die = 1) {
	echo '<pre>';
	print_r($data);
	if ($die == 1) {
		die;
	}
}

//Convert text to slug format
function convert_to_slug($text) {
	return strtolower(str_replace(' ', '-', preg_replace("/[^0-9A-Za-z ]/", '', $text)));
}

//Find data in multi array
function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }

    return false;
}

//Prepare data for multi insert
function prepare_multi_insert_data($keys, $values) {
	$row_length = count($keys);
	$nb_rows = count($values);
	$length = $nb_rows * $row_length;

	$args = implode(',', array_map(
		function($el) { return '('.implode(',', $el).')'; },
		array_chunk(array_fill(0, $length, '?'), $row_length)
	));

	$params = array();
	foreach($values as $row)
	{
	   foreach($row as $value)
	   {
		  $params[] = $value;
	   }
	}
	
	return array(
		'args'		=> $args,
		'values'	=> $params
	);
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/inc/connect.php';

$content_dir = 'wp-content/uploads';
$current_dir = sprintf('wp-content/uploads/%d/%s', date('Y'), date('m'));
if (!file_exists($current_dir)) {
	mkdir($current_dir, 0777, true);
}

$product_base_url = 'http://courses.works/product/';

$post_fields = array(
	"post_author",
	"post_date",
	"post_date_gmt",
	"post_content",
	"post_title",
	"post_status",
	"comment_status",
	"ping_status",
	"post_name",
	"guid",
	"post_type"
);
$post_data = $skus = $removed_skus = $product_extra_data = $wp_term_taxonomy = array();

$row = 1;
		
if ( ( $handle = fopen( 'feed_import_simple.csv', 'r' ) ) !== FALSE ) {
	while ( ( $data = fgetcsv( $handle, 10000, ',' ) ) !== FALSE ) {
		$num = count( $data );
		if ( $row != 1 ) {
			$start_date = time();

		
			$sku  = trim($data[2]);
			$slug = convert_to_slug($data[1]) . '-' . $sku;
					
			//Collect post data
			$post_data['posts'][$sku] = array(
				"1",
				date("Y-m-d H:i:s", time()),
				date("Y-m-d H:i:s", time()),
				addslashes($data[5]),
				$data[1],
				"publish",
				"closed",
				"closed",
				$slug,
				$product_base_url . $slug,
				"product"
			);
			
			$skus[$sku] = $slug;
			
			//Collect post meta data
			$post_data['meta'][$sku] = array(
				'_visibility'			=> 'visible',
				'_stock_status' 		=> ($data[30] == 1 ) ? 'instock' : 'outofstock',
				'_product_url'			=> $data[0],
				'_regular_price'		=> $data[7],
				'_price'				=> $data[7],
				'_sale_price'			=> $data[7] - 10,
				'_sku'					=> $data[2],
			);
			
			//Collect category data
			$category_slug = convert_to_slug($data[10]);
			$post_data['post_terms'][$sku][] = array(
				$data[10],
				$category_slug
			);
			
			//Add product type (product types is taxonomy term)
			$post_data['post_terms'][$sku][] = array(
				'external',
				'external'
			);
			
			//Collect unique categories
			$category_slug = convert_to_slug($data[10]);
			if (!in_array_r($data[10], $post_data['terms'])) {
				$post_data['terms'][$sku][] = array(
					$data[10],
					$category_slug
				);
			}
			
			//Add product attributes
			if (!empty($data[21])) {
				$post_data['post_terms'][$sku][] = array(
					$data[21],
					convert_to_slug($data[21])
				);
				
				if (!in_array_r($data[21], $post_data['terms'])) {
					$post_data['terms'][$sku][] = array(
						$data[21],
						convert_to_slug($data[21])
					);
				}
				
				if (!isset($post_data['attributes']['brand'])) {
					$post_data['attributes']['brand'] = array(
						'name'	=> 'Brand',
						'slug'	=> 'brand'
					);
				}
				
				$product_attributes = isset($post_data['meta'][$sku]['_product_attributes']) ? unserialize($post_data['meta'][$sku]['_product_attributes']) : array();
				if (!isset($product_attributes['pa_brand'])) {
					$product_attributes['pa_brand'] = array (
						'name' => 'pa_brand',
						'value' => '',
						'position' => '0',
						'is_visible' => 0,
						'is_variation' => 0,
						'is_taxonomy' => 1,
					);
					$post_data['meta'][$sku]['_product_attributes'] = serialize($product_attributes);
					$post_data['options']['pa_' . $post_data['attributes']['brand']['slug'] . '_children'] = serialize(array());
				}
			}
			
			if (!empty($data[22])) {				
				$post_data['post_terms'][$sku][] = array(
					$data[22],
					convert_to_slug($data[22])
				);
				
				if (!in_array_r($data[22], $post_data['terms'])) {
					$post_data['terms'][$sku][] = array(
						$data[22],
						convert_to_slug($data[22])
					);
				}
				
				if (!isset($post_data['attributes']['color'])) {
					$post_data['attributes']['color'] = array(
						'name'	=> 'Color',
						'slug'	=> 'color'
					);
				}
				
				$product_attributes = isset($post_data['meta'][$sku]['_product_attributes']) ? unserialize($post_data['meta'][$sku]['_product_attributes']) : array();
				if (!isset($product_attributes['pa_color'])) {
					$product_attributes['pa_color'] = array (
						'name' => 'pa_color',
						'value' => '',
						'position' => '0',
						'is_visible' => 0,
						'is_variation' => 0,
						'is_taxonomy' => 1,
					);
					$post_data['meta'][$sku]['_product_attributes'] = serialize($product_attributes);
					$post_data['options']['pa_' . $post_data['attributes']['color']['slug'] . '_children'] = serialize(array());
				}
			}
		}
		$row++;
		
		if ($row > 1001) {
			//err($post_data);
			//Insert our rows
			$post_insert_data = prepare_multi_insert_data($post_fields, $post_data['posts']);
			$pdo->beginTransaction();				
/* I */		$sql = 'INSERT INTO wp_posts (' . implode(', ', $post_fields) . ') VALUES ' . $post_insert_data['args'];
			$stmt = $pdo->prepare($sql);
			try {
				$stmt->execute($post_insert_data['values']);
			} catch (PDOException $e){
				echo $e->getMessage() . '<br />';
			}
			$pdo->commit();

			//Select inserted rows and map post ID to sku
/* S */		$sql = 'SELECT ID, post_name FROM wp_posts WHERE post_name IN(' . substr(str_repeat(', ?', count(array_keys($skus))), 2) . ')';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array_values($skus));
			while ($res = $stmt->fetch()) {
				$clean_sku = end(explode('-', $res['post_name']));
				if (isset($skus[$clean_sku])) {
					$skus[$clean_sku] = (int)$res['ID'];
				} else {
					$removed_skus[] = $clean_sku;
					unset($skus[$clean_sku]);
				}
			}

//Insert meta			
			//Insert terms
			$meta_keys = array(
				'post_id',
				'meta_key',
				'meta_value'
			);
			
			//Transform meta array for insert
			$metav = array();
			foreach ($post_data['meta'] as $sku => $meta) {
				if (isset($skus[$sku])) {
					foreach ($meta as $k => $v) {
						$metav[] = array(
							$skus[$sku],
							$k,
							$v
						);
					}
				}
			}
			if (count($metav) > 0) {
				$post_data['meta'] = $metav;
			}
			
			//Insert product meta data
			$meta_insert_data = prepare_multi_insert_data($meta_keys, $post_data['meta']);
			$pdo->beginTransaction();				
/* I */		$sql = 'INSERT INTO wp_postmeta (' . implode(', ', $meta_keys) . ') VALUES ' . $meta_insert_data['args'];
			$stmt = $pdo->prepare($sql);
			try {
				$stmt->execute($meta_insert_data['values']);
			} catch (PDOException $e){
				echo $e->getMessage() . '<br />';
			}
			$pdo->commit();
			
//Insert taxonomies			
			//Select all categories and check, if current products categories is missing
			$missing_terms = array();
/* S */		$sql = 'SELECT term_id, name, slug FROM wp_terms';
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
			while ($res = $stmt->fetch()) {
				$wp_terms[$res['slug']] = $res;
			}

			foreach ($post_data['terms'] as $sku => $terms) {
				foreach ($terms as $term) {
					if (!isset($wp_terms[$term[1]])) {
						$missing_terms[] = array(
							'name'	=> $term[0],
							'slug'	=> $term[1]
						);
					}
				}
			}

			//Insert missing categories, if needed
			if (count($missing_terms) > 0) {
				$terms_insert_data = prepare_multi_insert_data(array_keys(reset($missing_terms)), $missing_terms);
				$pdo->beginTransaction();				
/* I */			$sql = 'INSERT INTO wp_terms (' . implode(', ', array_keys(reset($missing_terms))) . ') VALUES ' . $terms_insert_data['args'];
				$stmt = $pdo->prepare($sql);
				try {
					$stmt->execute($terms_insert_data['values']);
				} catch (PDOException $e){
					echo $e->getMessage() . '<br />';
				}
				$pdo->commit();
			}
			
			//Select categories again with new terms
/* S */		$sql = 'SELECT term_id, name, slug FROM wp_terms';
			$stmt = $pdo->prepare($sql);
			//$stmt->execute(array('product_cat', 'product_type'));
			$stmt->execute();
			while ($res = $stmt->fetch()) {
				$wp_terms[$res['slug']] = $res;
			}

			//Select term taxonomy from wp_term_relationships
			$wp_term_taxonomy = $missing_wp_term_taxonomy = array();
/* S */		$sql = 'SELECT term_taxonomy_id, term_id, taxonomy FROM wp_term_taxonomy WHERE taxonomy IN(?, ?)';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array('product_cat', 'product_type'));
			while ($res = $stmt->fetch()) {
				$wp_term_taxonomy[$res['term_id'] . '-' . $res['taxonomy']] = $res;
			}

			foreach ($wp_terms as $sku => $term_data) {

				if ($term_data['slug'] == 'uncategorized') {
					continue;
				}

				$taxonomy = in_array($term_data['slug'], array('simple', 'grouped', 'variable', 'external')) ? 'product_type' : 'product_cat';
				if (!isset($wp_term_taxonomy[$term_data['term_id'] . '-' . $taxonomy])) {
					$missing_wp_term_taxonomy[] = array(
						'term_id'	=> $term_data['term_id'],
						'taxonomy'	=> $taxonomy
					);
				}
			}

			if (count($missing_wp_term_taxonomy) > 0) {
				$term_taxonomy_keys = array(
					'term_id',
					'taxonomy'
				);

				$term_taxonomy_insert_data = prepare_multi_insert_data(array_keys(reset($missing_wp_term_taxonomy)), $missing_wp_term_taxonomy);
				$pdo->beginTransaction();				
/* I */			$sql = 'INSERT INTO wp_term_taxonomy (' . implode(', ', array_keys(reset($missing_wp_term_taxonomy))) . ') VALUES ' . $term_taxonomy_insert_data['args'];
				$stmt = $pdo->prepare($sql);
				try {
					$stmt->execute($term_taxonomy_insert_data['values']);
				} catch (PDOException $e){
					echo $e->getMessage() . '<br />';
				}
				$pdo->commit();
			}

			//Select term taxonomy again with new items
			$wp_term_taxonomy = $missing_wp_term_taxonomy = array();
/* S */		$sql = 'SELECT term_taxonomy_id, term_id, taxonomy FROM wp_term_taxonomy WHERE taxonomy IN(?, ?)';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array('product_cat', 'product_type'));
			while ($res = $stmt->fetch()) {
				$wp_term_taxonomy[$res['term_id'] . '-' . $res['taxonomy']] = $res;
			}

			//Select term relations from wp_term_relationships
			$wp_term_relationships = $missing_wp_term_relationships = array();
/* S */		$sql = 'SELECT r.object_id, r.term_taxonomy_id FROM wp_term_relationships r LEFT JOIN wp_term_taxonomy t ON r.term_taxonomy_id=t.term_taxonomy_id WHERE t.taxonomy IN(?, ?)';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array('product_cat', 'product_type'));
			while ($res = $stmt->fetch()) {
				$wp_term_relationships[$res['object_id'] . '-' . $res['term_taxonomy_id']] = $res;
			}

			foreach ($post_data['post_terms'] as $sku => $terms_data) {
				foreach ($terms_data as $term_data) {
					if ($term_data['slug'] == 'uncategorized') {
						continue;
					}

					$taxonomy = in_array($term_data[1], array('simple', 'grouped', 'variable', 'external')) ? 'product_type' : 'product_cat';
					if (!isset($wp_term_relationships[$skus[$sku] . '-' . $wp_terms[$term_data[1]]])) {
						$missing_wp_term_relationships[] = array(
							'object_id'			=> $skus[$sku],
							'term_taxonomy_id'	=> $wp_term_taxonomy[$wp_terms[$term_data[1]]['term_id'] . '-' . $taxonomy]['term_taxonomy_id']
						);
					}
				}
			}

			if (count($missing_wp_term_relationships) > 0) {
				$term_relation_insert_data = prepare_multi_insert_data(array_keys(reset($missing_wp_term_relationships)), $missing_wp_term_relationships);
				$pdo->beginTransaction();				
/* I */			$sql = 'INSERT INTO wp_term_relationships (' . implode(', ', array_keys(reset($missing_wp_term_relationships))) . ') VALUES ' . $term_relation_insert_data['args'];
				$stmt = $pdo->prepare($sql);
				try {
					$stmt->execute($term_relation_insert_data['values']);
				} catch (PDOException $e){
					echo $e->getMessage() . '<br />';
				}
				$pdo->commit();
			}
			
			//Update categories count
/* I */		$sql = 'UPDATE wp_term_taxonomy SET count = (SELECT COUNT(*) FROM wp_term_relationships rel LEFT JOIN wp_posts po ON (po.ID = rel.object_id) WHERE rel.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id AND wp_term_taxonomy.taxonomy NOT IN ("link_category") AND po.post_status IN ("publish", "future"))';
			$stmt = $pdo->prepare($sql);
			try {
				$stmt->execute();
			} catch (PDOException $e){
				echo $e->getMessage() . '<br />';
			}
			
			//Insert options
			//First, create serialized object for attributes
			$product_attributes = $missing_attributes = array();
/* S */		$sql = 'SELECT attribute_id, attribute_name FROM wp_woocommerce_attribute_taxonomies';
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
			while ($res = $stmt->fetch()) {
				$product_attributes[$res['attribute_name']] = $res;
			}

			foreach ($post_data['attributes'] as $attribute => $attribute_data) {
				if (count($product_attributes)>0) {
					foreach ($product_attributes as $product_attribute) {
						if (!isset($product_attributes[$attribute])) {
							$missing_attributes[] = array(
								'attribute_name'	=> $attribute,
								'attribute_label'	=> $attribute_data['name'],
								'attribute_type'	=> 'select'
							);
						}
					}
				} else {
					$missing_attributes[] = array(
						'attribute_name'	=> $attribute,
						'attribute_label'	=> $attribute_data['name'],
						'attribute_type'	=> 'select'
					);
				}
			}

			if (count($missing_attributes) > 0) {
				$attributes_insert_data = prepare_multi_insert_data(array_keys(reset($missing_attributes)), $missing_attributes);
				$pdo->beginTransaction();				
/* I */			$sql = 'INSERT INTO wp_woocommerce_attribute_taxonomies (' . implode(', ', array_keys(reset($missing_attributes))) . ') VALUES ' . $attributes_insert_data['args'];
				$stmt = $pdo->prepare($sql);
				try {
					$stmt->execute($attributes_insert_data['values']);
				} catch (PDOException $e){
					echo $e->getMessage() . '<br />';
				}
				$pdo->commit();
			}
			
			//Select attributes again with new items
/* S */		$sql = 'SELECT * FROM wp_woocommerce_attribute_taxonomies';
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
			while ($res = $stmt->fetch()) {
				$product_attributes[$res['attribute_name']] = $res;
			}
			
			//Select _transient_wc_attribute_taxonomies from options
/* S */		$sql = 'SELECT option_value FROM wp_options WHERE option_name=?';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array('_transient_wc_attribute_taxonomies'));
			if ($wc_attribute_taxonomies = $stmt->fetch()) {
				$wc_attribute_taxonomies = unserialize($wc_attribute_taxonomies);
				foreach ($product_attributes as $product_attribute) {
					$is_exist = 0;
					foreach ($wc_attribute_taxonomies as $wc_attribute_taxonomy) {
						if ($product_attribute['attribute_name'] == $wc_attribute_taxonomy->attribute_name) {
							$is_exist = 1;
						}
					}
					
					if ($is_exists == 0) {
						$wc_attribute_taxonomies[] = (object) array(
							'attribute_id' => $product_attribute['attribute_id'],
							'attribute_name' => $product_attribute['attribute_name'],
							'attribute_label' => $product_attribute['attribute_label'],
							'attribute_type' => $product_attribute['attribute_type'],
							'attribute_orderby' => $product_attribute['attribute_order_by'],
							'attribute_public' => $product_attribute['attribute_public']
						);
					}
				}
			}
			
			$post_data['options']['_transient_wc_attribute_taxonomies'] = serialize($wc_attribute_taxonomies);
			
			//Update options or insert, if not exists
			$options_to_insert = array();
/* S */		$sql = 'SELECT * FROM wp_options WHERE option_name IN (' . substr(str_repeat(', ?', count($post_data['options'])), 2) . ')';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array_values($post_data['options']));
			while ($res = $stmt->fetch()) {
				$options_to_insert[$res['option_name']] = $res;
			}
			
			foreach ($post_data['options'] as $k => $v) {
				if (!isset($option_to_insert[$k])) {
					$option_to_insert[$k] = array(
						'option_id'		=> NULL,
						'option_name'	=> $k,
						'option_value'	=> $v,
						'autoload'		=> 'yes'
					);
				} else {
					$option_to_insert[$k]['option_value'] = $v;
				}
			}
			
			$options_insert_data = prepare_multi_insert_data(array_keys(reset($option_to_insert)), $option_to_insert);
			$pdo->beginTransaction();				
/* I */		$sql = 'INSERT INTO wp_options (' . implode(', ', array_keys(reset($option_to_insert))) . ') VALUES ' . $options_insert_data['args'] . ' ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)';
			$stmt = $pdo->prepare($sql);
			try {
				$stmt->execute($options_insert_data['values']);
			} catch (PDOException $e){
				echo $e->getMessage() . '<br />';
			}
			$pdo->commit();

			err($post_data);
			
			//Print execution time
			printf('Executed in %d seconds. %d rows processed. Used %dMb of memory', (time() - $start_date), $row, (memory_get_peak_usage(false)/1024/1024));
			die;
		}
	}
		
	fclose($handle);
}				

echo 'done';









//////////////////////
/*
$sql = 'select m.post_id as id, m.meta_value as url from wp_posts p LEFT JOIN wp_postmeta m ON p.ID=m.post_id where post_type="attachment" and m.meta_key="_wp_attached_file" and m.meta_value LIKE "%2017/08%"';
$stmt = $pdo->prepare($sql);
$stmt->execute(array());
echo '<pre>';
while ($row = $stmt->fetch()) {
	$sql2 = 'select meta_id, meta_value from wp_postmeta where post_id=? and meta_key="_wp_attachment_metadata"';
	$stmt2 = $pdo->prepare($sql2);
	$stmt2->execute(array($row['id']));
	$data = $stmt2->fetch();
	$meta_data = unserialize($data['meta_value']);
	if (!isset($meta_data['width'])) {
		$image_data = getimagesize('/var/www/vhosts/works/htdocs/courses/' . $row['url']);
		$w = $image_data[0];
		$h = $image_data[1];
		$res = array(
			'width' => $w,
			'height' => $h,
			'file' => str_replace('wp-content/uploads/', '', $row['url']),
			'sizes'	=> array(
				'large' => array(
					'width' => $w,
					'height' => $h,
					'file' => str_replace('wp-content/uploads/', '', $row['url']),
					'mime' => $image_data['mime'],
				),
				'shop_single' => array(
					'width' => $w,
					'height' => $h,
					'file' => str_replace('wp-content/uploads/', '', $row['url']),
					'mime' => $image_data['mime'],
				)
			),
			'image_meta' => array(
				'aperture' => 0,
				'credit' => '',
				'camera' => '',
				'caption' => '',
				'created_timestamp' => 0,
				'copyright' => '',
				'focal_length' => 0,
				'iso' => 0,
				'shutter_speed' => 0,
				'title' => '',
				'orientation' => 0,
				'keywords' => array()
			)
		);
		echo $data['meta_id'] . "<br />";
		$sql3 = 'update wp_postmeta set meta_value=? where meta_id=?';
		$stmt3 = $pdo->prepare($sql3);
		$stmt3->execute(array(serialize($res), $data['meta_id']));
	} else {
		//print_r($meta_data);die;
	}
}
die;
*/
//////////////////////

/*
//Insert product meta
$meta_data = array(
	array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_visibility",
		"meta_value"	=> "visible"
	),
	array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_stock_status",
		"meta_value"	=> ($data[30] == 1 ) ? 'instock' : 'outofstock'
	),
	array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_product_url",
		"meta_value"	=> $data[0]
	),
	array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_regular_price",
		"meta_value"	=> $data[7]
	),
	array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_price",
		"meta_value"	=> $data[7]
	),
	array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_sku",
		"meta_value"	=> $data[2]
	),
);

//Insert image
if(!empty($data[4])) {
	$file = file_get_contents($data[4]);
	file_put_contents($current_dir . '/' . basename($data[4]), $file);
	$post_data = array(
		"post_author" => "1",
		"post_date" => "0000-00-00 00:00:00",
		"post_date_gmt" => "0000-00-00 00:00:00",
		"post_content" => "",
		"post_title" => basename($data[4]),
		"post_excerpt" => "",
		"post_status" => "inherit",
		"comment_status" => "open",
		"ping_status" => "closed",
		"post_password" => "",
		"post_name" => basename($data[4]),
		"to_ping" => "",
		"pinged" => "",
		"post_modified" => "",
		"post_modified_gmt" => "",
		"post_content_filtered" => "",
		"post_parent" => $product_id,
		"guid" => "http://courses.works/product/" . strtolower(str_replace(' ', '-', preg_replace("/[^A-Za-z ]/", '', $data[1]))) . "/" . basename($data[4]) . "/",
		"menu_order" => "",
		"post_type" => "attachment",
		"post_mime_type" => mime_content_type($current_dir . '/' . basename($data[4])),
		"comment_count" => ""
	);
	
	//Insert thumb
	$sql = 'INSERT INTO wp_posts (' . implode(', ', array_keys($post_data)) . ') VALUES (' . substr(str_repeat(', ?', count(array_keys($post_data))), 2) . ')';
	//echo $sql;die;
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array_values($post_data));
	$thumb_id = $pdo->lastInsertId();
	
	$thumb_meta_data = array(
		array(
			"post_id"		=> $thumb_id,
			"meta_key"		=> "_wp_attached_file",
			"meta_value"	=> $current_dir . '/' . basename($data[4])
		),
		array(
			"post_id"		=> $thumb_id,
			"meta_key"		=> "_wp_attachment_metadata",
			"meta_value"	=> $thumb_id
		),
	);
	
	foreach ($thumb_meta_data as $meta) {
		$sql = 'INSERT INTO wp_postmeta (' . implode(', ', array_keys($meta)) . ') VALUES (' . substr(str_repeat(', ?', count(array_keys($meta))), 2) . ')';
		$stmt = $pdo->prepare($sql);
		$stmt->execute(array_values($meta));
	}
	
	$meta_data[] = array(
		"post_id"		=> $product_id,
		"meta_key"		=> "_thumbnail_id",
		"meta_value"	=> $thumb_id
	);
}

//Insert product meta
//echo '<pre>'; print_r($meta_data); die;
foreach ($meta_data as $meta) {
	$sql = 'INSERT INTO wp_postmeta (' . implode(', ', array_keys($meta)) . ') VALUES (' . substr(str_repeat(', ?', count(array_keys($meta))), 2) . ')';
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array_values($meta));
}
//*/
//}


//Set product type to external
/*
$sql = "SELECT term_id FROM wp_terms WHERE slug=?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array('external'));
if ($res = $stmt->fetch()) {
	$term_id = $res['term_id'];
} else {
	$sql = 'INSERT INTO wp_terms (name, slug) VALUES (?, ?)';
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array('external', 'external'));
	$term_id = $pdo->lastInsertId();
	
}

$sql = "SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=? AND taxonomy='product_type'";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($term_id));
if ($res = $stmt->fetch()) {
	$term_taxonomy_id = $res['term_taxonomy_id'];
} else {
	$sql = 'INSERT INTO wp_term_taxonomy (term_taxonomy_id, term_id, taxonomy) VALUES (?, ?, ?)';
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array(NULL, $term_id, 'product_type'));
	$term_taxonomy_id = $pdo->lastInsertId();
	
}

$sql = 'INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (?, ?)';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($product_id, $term_taxonomy_id));

//Insert category
$sql = 'SELECT term_id FROM wp_terms WHERE name=?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($data[10]));
if ($res = $stmt->fetch()) {
	$term_id = $res['term_id'];
} else {
	$sql = 'INSERT INTO wp_terms (name, slug) VALUES (?, ?)';
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array($data[10], strtolower(str_replace(' ', '-', preg_replace("/[^A-Za-z ]/", '', $data[10])))));
	$term_id = $pdo->lastInsertId();
	
}

$sql = "SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE term_id=? AND taxonomy='product_cat'";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($term_id));
if ($res = $stmt->fetch()) {
	$term_taxonomy_id = $res['term_taxonomy_id'];
} else {
	$sql = 'INSERT INTO wp_term_taxonomy (term_taxonomy_id, term_id, taxonomy) VALUES (?, ?, ?)';
	$stmt = $pdo->prepare($sql);
	$stmt->execute(array(NULL, $term_id, 'product_cat'));
	$term_taxonomy_id = $pdo->lastInsertId();
	
}

$sql = 'INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (?, ?)';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($product_id, $term_taxonomy_id));
*/
