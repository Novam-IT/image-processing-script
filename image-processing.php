<?php 

add_filter('wp_handle_upload', 'handle_upload_convert_to_webp');

function handle_upload_convert_to_webp($upload) {
    $current_user = wp_get_current_user();
    $max_width = 1920; // here you can set a max-width of the pictures
   
  // if you want you can set the max-width to for diffrent roles
  
    if (in_array('some-role', $current_user->roles)) {
        $max_width = 1280;
    }
    if (in_array('some-role', $current_user->roles)) {
        $max_width = 800;
    }
    
    if ($upload['type'] == 'image/jpeg' || $upload['type'] == 'image/png' || $upload['type'] == 'image/gif') {
        $file_path = $upload['file']; // We change the filename later, since the upload is not connected to the post yet

        if (extension_loaded('imagick')) {
            $image_editor = wp_get_image_editor($file_path);
            if (!is_wp_error($image_editor)) {
                $sizes = $image_editor->get_size();
                if ($sizes['width'] > $max_width) {
                    $image_editor->resize($max_width, null, false);
                }

                $quality = 80; // here you can adjust the quality

                $file_info = pathinfo($file_path);
                $dirname = $file_info['dirname'];
                $filename = $file_info['filename'];

                $new_file_path = $dirname . '/' . $filename . '.webp';

                $saved_image = $image_editor->save($new_file_path, 'image/webp', ['quality' => $quality]);
                if (!is_wp_error($saved_image) && file_exists($saved_image['path'])) {
                    $upload['file'] = $saved_image['path'];
                    $upload['url'] = str_replace(basename($upload['url']), basename($saved_image['path']), $upload['url']);
                    $upload['type'] = 'image/webp';

                    @unlink($file_path);
                }
            }
        }
    }
    return $upload;
}

add_action('add_attachment', 'rename_uploaded_file_based_on_post');

function rename_uploaded_file_based_on_post($attachment_id) {
    $attachment = get_post($attachment_id);
    $parent_post_id = $attachment->post_parent;

    if ($parent_post_id) {
        $parent_post = get_post($parent_post_id);
        $post_title = $parent_post->post_title;

        $sanitized_title = sanitize_title($post_title);

        $file_path = get_attached_file($attachment_id);
        $upload_dir = wp_upload_dir();
        $file_info = pathinfo($file_path);
        $dirname = $file_info['dirname'];
        $extension = $file_info['extension'];

        $counter = '';
        $new_filename = $sanitized_title . '.' . $extension;
        $new_file_path = $dirname . '/' . $new_filename;

        while (file_exists($new_file_path)) {
            $counter = $counter === '' ? 2 : $counter + 1;
            $new_filename = $sanitized_title . '-' . $counter . '.' . $extension;
            $new_file_path = $dirname . '/' . $new_filename;
        }

        $did_rename = rename($file_path, $new_file_path);

        if ($did_rename) {
            update_attached_file($attachment_id, $new_file_path);

            $relative_path = str_replace(ABSPATH, '', $new_file_path);
            $new_url = site_url('/') . $relative_path;

            wp_update_post([
                'ID' => $attachment_id,
                'guid' => $new_url,
            ]);

            $metadata = wp_generate_attachment_metadata($attachment_id, $new_file_path);
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }
}
