<?php
if (!defined('ABSPATH')) exit;

define('LITANO_VERSION','1.1.0');

function litano_setup(){
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('custom-logo');
  register_nav_menus(['primary'=>'Primary Menu','footer'=>'Footer Menu']);
}
add_action('after_setup_theme','litano_setup');

function litano_assets(){
  wp_enqueue_style('litano-main',get_template_directory_uri().'/assets/css/main.css',[],LITANO_VERSION);
  wp_enqueue_script('litano-main',get_template_directory_uri().'/assets/js/main.js',[],LITANO_VERSION,true);
  wp_localize_script('litano-main','LITANO',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('litano_nonce'),'currency'=>'USD']);
}
add_action('wp_enqueue_scripts','litano_assets');

function litano_register_product(){
  register_post_type('litano_product',[
    'labels'=>['name'=>'Bullion Products','singular_name'=>'Bullion Product','menu_name'=>'LITANO Bullion','add_new_item'=>'Add Bullion Product','edit_item'=>'Edit Bullion Product'],
    'public'=>true,'show_ui'=>true,'menu_icon'=>'dashicons-money-alt','supports'=>['title','editor','excerpt','thumbnail'],'has_archive'=>true,'rewrite'=>['slug'=>'bullion'],'show_in_rest'=>true
  ]);
  register_taxonomy('litano_metal','litano_product',['labels'=>['name'=>'Metals','singular_name'=>'Metal'],'public'=>true,'hierarchical'=>true,'show_in_rest'=>true]);
}
add_action('init','litano_register_product');

function litano_product_box(){add_meta_box('litano_product_data','Bullion Details','litano_product_box_html','litano_product','normal','high');}
add_action('add_meta_boxes','litano_product_box');
function litano_product_box_html($post){
  wp_nonce_field('litano_save_product','litano_product_nonce');
  $fields=['sku'=>'SKU / Product Code','metal'=>'Metal','weight'=>'Weight','purity'=>'Purity / Fineness','refinery'=>'Refinery / Mint','origin'=>'Origin','price'=>'Price (USD)','compare_price'=>'Reference / Previous Price (USD)','stock'=>'Stock','status'=>'Availability','premium'=>'Premium / Pricing Note','shipping'=>'Shipping / Delivery'];
  foreach($fields as $k=>$label){$v=get_post_meta($post->ID,'_litano_'.$k,true);echo '<p><label><strong>'.esc_html($label).'</strong></label><br><input style="width:100%;max-width:720px" name="litano_'.$k.'" value="'.esc_attr($v).'"></p>';}
}
function litano_save_product($post_id){
  if(!isset($_POST['litano_product_nonce'])||!wp_verify_nonce($_POST['litano_product_nonce'],'litano_save_product')) return;
  if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE) return;
  if(!current_user_can('edit_post',$post_id)) return;
  foreach(['sku','metal','weight','purity','refinery','origin','price','compare_price','stock','status','premium','shipping'] as $k){if(isset($_POST['litano_'.$k])) update_post_meta($post_id,'_litano_'.$k,sanitize_text_field($_POST['litano_'.$k]));}
}
add_action('save_post_litano_product','litano_save_product');

function litano_price($id){$v=str_replace(',','',get_post_meta($id,'_litano_price',true));return is_numeric($v)?(float)$v:0;}
function litano_money($v){return '$'.number_format((float)$v,2,'.',',');}

function litano_register_order(){
 register_post_type('litano_order',['labels'=>['name'=>'LITANO Orders','singular_name'=>'LITANO Order','menu_name'=>'LITANO Orders'],'public'=>false,'show_ui'=>true,'menu_icon'=>'dashicons-clipboard','supports'=>['title']]);
}
add_action('init','litano_register_order');

function litano_order_box(){add_meta_box('litano_order_data','Order Details','litano_order_box_html','litano_order','normal','high');}
add_action('add_meta_boxes','litano_order_box');
function litano_order_box_html($post){$d=get_post_meta($post->ID,'_litano_order_data',true);if(!is_array($d)){echo '<p>No data.</p>';return;}echo '<pre style="white-space:pre-wrap">'.esc_html(print_r($d,true)).'</pre>';}

function litano_cart_url(){return home_url('/cart/');}
function litano_checkout_url(){return home_url('/checkout/');}

function litano_submit_order(){
 check_ajax_referer('litano_nonce','nonce');
 $name=sanitize_text_field($_POST['name']??'');$email=sanitize_email($_POST['email']??'');$phone=sanitize_text_field($_POST['phone']??'');$document=sanitize_text_field($_POST['document']??'');
 $address=sanitize_text_field($_POST['address']??'');$city=sanitize_text_field($_POST['city']??'');$state=sanitize_text_field($_POST['state']??'');$zip=sanitize_text_field($_POST['zip']??'');$payment=sanitize_text_field($_POST['payment']??'');
 $items=json_decode(wp_unslash($_POST['items']??'[]'),true);if(!$name||!$email||!is_array($items)||!$items) wp_send_json_error(['message'=>'Please complete the required fields and add at least one product.'],400);
 $clean=[];$total=0;foreach($items as $i){$id=absint($i['id']??0);$qty=max(1,absint($i['qty']??1));if(get_post_type($id)!=='litano_product') continue;$price=litano_price($id);$sub=$price*$qty;$total+=$sub;$clean[]=['id'=>$id,'name'=>get_the_title($id),'qty'=>$qty,'price'=>$price,'subtotal'=>$sub];}
 if(!$clean) wp_send_json_error(['message'=>'Invalid cart.'],400);
 $order_id=wp_insert_post(['post_type'=>'litano_order','post_status'=>'publish','post_title'=>'LITANO Order - '.current_time('m/d/Y H:i').' - '.$name]);
 $data=['name'=>$name,'email'=>$email,'phone'=>$phone,'document'=>$document,'address'=>$address,'city'=>$city,'state'=>$state,'zip'=>$zip,'payment'=>$payment,'items'=>$clean,'total'=>litano_money($total),'market'=>'United States'];
 update_post_meta($order_id,'_litano_order_data',$data);
 wp_mail(get_option('admin_email'),'New LITANO Order #'.$order_id,"New bullion order request\n\nCustomer: $name\nEmail: $email\nPhone: $phone\nTotal: ".litano_money($total));
 wp_send_json_success(['message'=>'Order request received.','orderId'=>$order_id,'total'=>litano_money($total)]);
}
add_action('wp_ajax_litano_submit_order','litano_submit_order');add_action('wp_ajax_nopriv_litano_submit_order','litano_submit_order');

function litano_catalog_shortcode(){
 $q=new WP_Query(['post_type'=>'litano_product','post_status'=>'publish','posts_per_page'=>-1]);ob_start();echo '<div class="litano-grid">';
 if($q->have_posts()){while($q->have_posts()){$q->the_post();$id=get_the_ID();$price=litano_price($id);echo '<article class="litano-product-card"><a class="litano-product-image" href="'.esc_url(get_permalink()).'">';if(has_post_thumbnail())the_post_thumbnail('large');else echo '<div class="litano-placeholder">LITANO</div>';echo '</a><div class="litano-product-body"><div class="litano-kicker">'.esc_html(get_post_meta($id,'_litano_metal',true)).'</div><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div class="litano-specs">';foreach(['weight','purity'] as $k){$v=get_post_meta($id,'_litano_'.$k,true);if($v)echo '<span>'.esc_html($v).'</span>';}echo '</div><div class="litano-price">'.esc_html(litano_money($price)).'</div><button class="litano-add-cart" data-id="'.$id.'" data-name="'.esc_attr(get_the_title()).'" data-price="'.$price.'">Add to Cart</button></div></article>';}}else echo '<div class="litano-empty">No bullion products published yet.</div>';wp_reset_postdata();echo '</div>';return ob_get_clean();
}
add_shortcode('litano_catalog','litano_catalog_shortcode');

function litano_cart_shortcode(){ob_start();?><div class="litano-cart-shell"><h2>Your Cart</h2><div id="litano-cart-items"></div><div class="litano-cart-footer"><div><span>Total</span><strong id="litano-cart-total">$0.00</strong></div><a class="litano-primary-btn" href="<?php echo esc_url(litano_checkout_url()); ?>">Proceed to Checkout</a></div></div><?php return ob_get_clean();}
add_shortcode('litano_cart','litano_cart_shortcode');

function litano_checkout_shortcode(){ob_start();?><div class="litano-checkout"><div><div class="litano-eyebrow">CHECKOUT</div><h2>Complete Your Order Request</h2><form id="litano-checkout-form"><div class="litano-two"><input required name="name" placeholder="Full Name"><input required type="email" name="email" placeholder="Email"></div><div class="litano-two"><input name="phone" placeholder="Phone"><input name="document" placeholder="Tax ID / Business ID (optional)"></div><input name="address" placeholder="Street Address"><div class="litano-three"><input name="city" placeholder="City"><input name="state" placeholder="State"><input name="zip" placeholder="ZIP Code"></div><select name="payment"><option value="Bank Wire">Bank Wire</option><option value="Commercial Review">Commercial Review</option></select><button class="litano-primary-btn" type="submit">Submit Order Request</button><div id="litano-order-response"></div></form></div><aside class="litano-checkout-summary"><h3>Order Summary</h3><div id="litano-checkout-items"></div><div class="litano-summary-total"><span>Total</span><strong id="litano-checkout-total">$0.00</strong></div><p>All bullion orders are subject to inventory confirmation, customer verification, pricing confirmation, applicable taxes, shipping restrictions and final settlement instructions.</p></aside></div><?php return ob_get_clean();}
add_shortcode('litano_checkout','litano_checkout_shortcode');

function litano_create_pages(){foreach(['Bullion'=>['bullion-products','[litano_catalog]'],'Cart'=>['cart','[litano_cart]'],'Checkout'=>['checkout','[litano_checkout]']] as $title=>$cfg){if(!get_page_by_path($cfg[0]))wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$cfg[0],'post_content'=>$cfg[1]]);}flush_rewrite_rules();}
add_action('after_switch_theme','litano_create_pages');
