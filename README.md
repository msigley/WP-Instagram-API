[![ko-fi](https://www.ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/A0A01FORH)
# WP-Instagram-API
Instagram Basic Display API plugin for Wordpress

## Setup
Follow the directions here to create a Basic Display App for your Wordpress site:
https://developers.facebook.com/docs/instagram-basic-display-api/getting-started

### Change the following settings under Products > Instagram > Basic Display:

Valid OAuth Redirect URIs
```https://<youwordpresssiteurl.com>/wp-admin/```

Deauthorize:
```https://<youwordpresssiteurl.com>/```

Data Deletion Request URL:
```https://<youwordpresssiteurl.com>/```

User Token Generator:
Add the Instagram account here you will be pulling content from. You can pull content from one account at a time and accounts you own/have the credentials for.

**You do not need to perform an App Review.**

### The following definitions need to be added to your wp-config.php file:
```php
/**
 * Instagram API Creds
 */
define('INSTAGRAM_CLIENT_ID', '<your instagram app id>');
define('INSTAGRAM_CLIENT_SECRET', '<your instagram app secret>');
```

### Upload the plugin to your WordPress site and activate it
After activating the plugin you will see a message in the Wordpress Admin asking you to authenticate the Instagram account you added as a test user in the previous step.

## API Functions
### instagram_get_user_items
Performs a call to ```me/media``` as described here:
https://developers.facebook.com/docs/instagram-basic-display-api/guides/getting-profiles-and-media
Handles item pagination for large request limits.
Returns the json decoded response directly from the Basic Display API.

#### Example Code
This PHP code pulls the six most recent items from your instagram account.
```php
<?php 
$instagram_posts = instagram_get_user_items( array( 'limit' => 6 ) );
if( !empty( $instagram_posts ) ):
	?>
  <div class="instagram-feed" data-chaos-modal-gallery="instagram">
    <?php
    for( $i = 0; $i < $instagram_posts->count; $i++ ):
      $instagram_post = $instagram_posts->data[$i];
      $title = htmlspecialchars( $instagram_post->caption );
      $caption = htmlspecialchars( nl2br( $instagram_post->caption ) ) . htmlspecialchars( '<br /><br /><a href="'.$instagram_post->permalink.'">View Full Post by @'.$instagram_post->username.' on Instagram</a>' );
      $thumbnail = $instagram_post->media_url;
      if( !empty( $instagram_post->thumbnail_url ) )
        $thumbnail = $instagram_post->thumbnail_url; 
      if( empty($thumbnail) )
        continue; //Skip instagrams with no valid images.
      ?><div class="item">
        <a href="<?php echo $instagram_post->media_url; ?>" class="chaos-modal-link" title="<?php echo $title; ?>" data-chaos-modal-caption="<?php echo $caption; ?>">
          <img src="<?php echo $thumbnail; ?>" alt="<?php echo $title; ?>" />
        </a>
      </div><!--.item--><?php
    endfor;
    ?>
  </div><!--.instagram-feed-->
	<?php
endif;
?>
```
The HTML is formatted for use with the jquery-chaos-modal script here:
https://github.com/msigley/jquery-chaos-modal
