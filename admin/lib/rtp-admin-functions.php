<?php
/**
 * rtPanel Admin Functions
 *
 * @package rtPanel
 *
 * @since rtPanel 2.0
 */

global $rtp_general, $rtp_post_comments, $rtp_hooks, $rtp_version;

/**
 * Data validation for rtPanel General Options
 * 
 * @uses $rtp_general array
 * @return Array
 *
 * @since rtPanel 2.0
 */
function rtp_general_validate($input) {
    global $rtp_general;
    require_once( ABSPATH . '/wp-admin/includes/class-wp-filesystem-base.php' );
    require_once( ABSPATH . '/wp-admin/includes/class-wp-filesystem-direct.php' );
    @$file_object = new WP_Filesystem_Direct;
    $default = rtp_theme_setup_values();

    if (isset($_POST['rtp_submit'])) {
        add_filter('intermediate_image_sizes_advanced', 'rtp_create_favicon');
        if ('image' == $input['logo_use'] && !empty($_FILES) && isset($_FILES['html-upload-logo']) && $_FILES['html-upload-logo']['size']) {
            if (substr($_FILES['html-upload-logo']['type'], 0, 5) == 'image') {
                $id = media_handle_upload('html-upload-logo', 0);
                if (is_wp_error($id)) {
                    if (!empty($id->errors['upload_error']))
                        $logo_errors = $id->errors['upload_error'];
                    if (!empty($logo_errors)) {
                        foreach ($logo_errors as $logo_error) {
                            add_settings_error('html-upload-logo', 'html-upload-logo', $logo_error, 'error');
                        }
                    }
                } else {
                    $img_src = wp_get_attachment_image_src($id, 'full', true);
                    $input['logo_upload'] = $img_src[0];
                    $input['logo_id'] = $id;
                    $input['logo_width'] = $img_src[1];
                    $input['logo_height'] = $img_src[2];
                    add_settings_error('html-upload-logo', 'html-upload-logo', __('Logo & Favicon Settings Updated', 'rtPanel'), 'updated');
                }
            } else {
                add_settings_error('html-upload-logo', 'html-upload-logo', __('Please upload a valid image file.', 'rtPanel'), 'error');
            }
        }
        if ('image' == $input['favicon_use'] && !empty($_FILES) && isset($_FILES['html-upload-fav']) && $_FILES['html-upload-fav']['size']) {
// Upload File button was clicked
            if (substr($_FILES['html-upload-fav']['type'], 0, 5) == 'image') {
                $id = media_handle_upload('html-upload-fav', 0);
                if (is_wp_error($id)) {
                    if (!empty($id->errors['upload_error']))
                        $fav_errors = $id->errors['upload_error'];
                    if (!empty($fav_errors)) {
                        foreach ($fav_errors as $fav_error) {
                            add_settings_error('html-upload-fav', 'html-upload-fav', $fav_error, 'error');
                        }
                    }
                } else {
                    $img_src = wp_get_attachment_image_src($id, 'favicon', true);
                    $input['favicon_upload'] = $img_src[0];
                    $input['favicon_id'] = $id;
                    add_settings_error('html-upload-fav', 'html-upload-fav', __('Logo & Favicon Settings Updated', 'rtPanel'), 'updated');
                }
            } else {
                add_settings_error('html-upload-fav', 'html-upload-fav', __('Please upload a valid image file.', 'rtPanel'), 'error');
            }
        } elseif ('logo' == $input['favicon_use']) {
            if (RTP_IMG_FOLDER_URL . '/rtp-logo.jpg' == $input['logo_upload']) {
                $input['favicon_upload'] = RTP_IMG_FOLDER_URL . '/favicon.ico';
                $input['favicon_id'] = 0;
            } else {
                $img_src = wp_get_attachment_image_src($input['logo_id'], 'favicon', true);
                $input['favicon_upload'] = $img_src[0];
                $input['favicon_id'] = $input['logo_id'];
            }
        }
        remove_filter('intermediate_image_sizes_advanced', 'rtp_create_favicon');

        if ('image' != $input['logo_use']) {
            $input['login_head'] = $rtp_general['login_head'];
        }

        if (!empty($input['feedburner_url'])) {
            $result = wp_remote_get($input['feedburner_url']);
            if (is_wp_error($result) || $result["response"]["code"] != 200) {
                $input['feedburner_url'] = $rtp_general['feedburner_url'];
                add_settings_error('feedburner_url', 'invalid_feedburner_url', __('The FeedBurner URL is not a valid url. The changes made have been reverted.', 'rtPanel'));
            } elseif ($input['feedburner_url'] != $rtp_general['feedburner_url']) {
                add_settings_error('feedburner_url', 'valid_feedburner_url', __('The FeedBurner Settings have been updated.', 'rtPanel'), 'updated');
            }
        }

        if (trim($input['fb_app_id']) != $rtp_general['fb_app_id']) {
            $input['fb_app_id'] = trim($input['fb_app_id']);
            add_settings_error('fb_app_id', 'valid_fb_app_id', __('The Facebook App ID has been updated.', 'rtPanel'), 'updated');
        }

        if (trim($input['fb_admins']) != $rtp_general['fb_admins']) {
            $input['fb_admins'] = trim($input['fb_admins']);
            add_settings_error('fb_admins', 'valid_fb_admins', __('The Facebook Admin ID(s) has been updated.', 'rtPanel'), 'updated');
        }

        if (!empty($input['search_code'])) {
            if (!preg_match('/customSearchControl.draw\(\'cse\'(.*)\)\;/i', $input['search_code']) && !preg_match('/\<gcse:(searchresults-only|searchresults|search).*\>\<\/gcse:(searchresults-only|searchresults|search)\>/i', $input['search_code'])) {
                $input['search_code'] = $rtp_general['search_code'];
                add_settings_error('search_code', 'invalid_search_code', __('Google Search Code Error : While generating the code the layout option should either be "full-width" or "compact". The changes made have been reverted.', 'rtPanel'));
            } elseif ($input['search_code'] != $rtp_general['search_code']) {
                add_settings_error('search_code', 'valid_search_code', __('Google Custom Search Integration has been updated.', 'rtPanel'), 'updated');
            }
        }

        if (isset($_POST['rtsocial-activate']) && ( $_POST['rtsocial-activate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_rtsocial_activate'];
            if (!wp_verify_nonce($nonce, RTP_SOCIAL . '-activate')) {
                add_settings_error('activate-plugin', 'failure_plugin_activation', __('You do not have sufficient permissions to activate this plugin.', 'rtPanel'));
            } else {
                activate_plugin(RTP_SOCIAL);
                add_settings_error('activate-plugin', 'plugin_activation', __('rtSocial has been Activated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['rtsocial-deactivate']) && ( $_POST['rtsocial-deactivate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_rtsocial_deactivate'];
            if (!wp_verify_nonce($nonce, RTP_SOCIAL . '-deactivate')) {
                add_settings_error('deactivate-plugin', 'failure_plugin_deactivation', __('You do not have sufficient permissions to deactivate this plugin.', 'rtPanel'));
            } else {
                deactivate_plugins(array(RTP_SOCIAL));
                add_settings_error('deactivate-plugin', 'plugin_activation', __('rtSocial has been Deactivated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['rtsocial-delete']) && ( $_POST['rtsocial-delete'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_rtsocial_delete'];
            if (!wp_verify_nonce($nonce, RTP_SOCIAL . '-delete')) {
                add_settings_error('delete-plugin', 'failure_plugin_deletion', __('You do not have sufficient permissions to delete this plugin.', 'rtPanel'));
            } else {
                delete_plugins(array(RTP_SOCIAL));
                add_settings_error('delete-plugin', 'plugin_deletion', __('rtSocial has been Deleted.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['rtp-hooks-editor-activate']) && ( $_POST['rtp-hooks-editor-activate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_rtp_hooks_editor_activate'];
            if (!wp_verify_nonce($nonce, RTP_HOOKS_EDITOR . '-activate')) {
                add_settings_error('activate-plugin', 'failure_plugin_activation', __('You do not have sufficient permissions to activate this plugin.', 'rtPanel'));
            } else {
                activate_plugin(RTP_HOOKS_EDITOR);
                add_settings_error('activate-plugin', 'plugin_activation', __('rtPanel Hooks Editor Plugin has been Activated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['rtp-hooks-editor-deactivate']) && ( $_POST['rtp-hooks-editor-deactivate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_rtp_hooks_editor_deactivate'];
            if (!wp_verify_nonce($nonce, RTP_HOOKS_EDITOR . '-deactivate')) {
                add_settings_error('deactivate-plugin', 'failure_plugin_deactivation', __('You do not have sufficient permissions to deactivate this plugin.', 'rtPanel'));
            } else {
                deactivate_plugins(array(RTP_HOOKS_EDITOR));
                add_settings_error('deactivate-plugin', 'plugin_activation', __('rtPanel Hooks Editor Plugin has been Deactivated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['rtp-hooks-editor-delete']) && ( $_POST['rtp-hooks-editor-delete'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_rtp_hooks_editor_delete'];
            if (!wp_verify_nonce($nonce, RTP_HOOKS_EDITOR . '-delete')) {
                add_settings_error('delete-plugin', 'failure_plugin_deletion', __('You do not have sufficient permissions to delete this plugin.', 'rtPanel'));
            } else {
                delete_plugins(array(RTP_HOOKS_EDITOR));
                add_settings_error('delete-plugin', 'plugin_deletion', __('rtPanel Hooks Editor Plugin has been Deleted.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['subscribe-activate']) && ( $_POST['subscribe-activate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_subscribe_activate'];
            if (!wp_verify_nonce($nonce, RTP_SUBSCRIBE_TO_COMMENTS . '-activate')) {
                add_settings_error('activate-plugin', 'failure_plugin_activation', __('You do not have sufficient permissions to activate this plugin.', 'rtPanel'));
            } else {
                activate_plugin(RTP_SUBSCRIBE_TO_COMMENTS);
                add_settings_error('activate-plugin', 'plugin_activation', __('Subscribe to Comments Plugin has been Activated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['subscribe-deactivate']) && ( $_POST['subscribe-deactivate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_subscribe_deactivate'];
            if (!wp_verify_nonce($nonce, RTP_SUBSCRIBE_TO_COMMENTS . '-deactivate')) {
                add_settings_error('deactivate-plugin', 'failure_plugin_deactivation', __('You do not have sufficient permissions to deactivate this plugin.', 'rtPanel'));
            } else {
                deactivate_plugins(array(RTP_SUBSCRIBE_TO_COMMENTS));
                add_settings_error('deactivate-plugin', 'plugin_activation', __('Subscribe to Comments Plugin has been Deactivated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['subscribe-delete']) && ( $_POST['subscribe-delete'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_subscribe_delete'];
            if (!wp_verify_nonce($nonce, RTP_SUBSCRIBE_TO_COMMENTS . '-delete')) {
                add_settings_error('delete-plugin', 'failure_plugin_deletion', __('You do not have sufficient permissions to delete this plugin.', 'rtPanel'));
            } else {
                delete_plugins(array(RTP_SUBSCRIBE_TO_COMMENTS));
                add_settings_error('delete-plugin', 'plugin_deletion', __('Subscribe to Comments Plugin has been Deleted.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['yoast_seo-activate']) && ( $_POST['yoast_seo-activate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_yoast_seo_activate'];
            if (!wp_verify_nonce($nonce, RTP_YOAST_SEO . '-activate')) {
                add_settings_error('activate-plugin', 'failure_plugin_activation', __('You do not have sufficient permissions to activate this plugin.', 'rtPanel'));
            } else {
                activate_plugin(RTP_YOAST_SEO);
                add_settings_error('activate-plugin', 'plugin_activation', __('Yoast WordPress SEO Plugin has been Activated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['yoast_seo-deactivate']) && ( $_POST['yoast_seo-deactivate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_yoast_seo_deactivate'];
            if (!wp_verify_nonce($nonce, RTP_YOAST_SEO . '-deactivate')) {
                add_settings_error('deactivate-plugin', 'failure_plugin_deactivation', __('You do not have sufficient permissions to deactivate this plugin.', 'rtPanel'));
            } else {
                deactivate_plugins(array(RTP_YOAST_SEO));
                add_settings_error('deactivate-plugin', 'plugin_deactivation', __('Yoast WordPress SEO Plugin has been Deactivated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['yoast_seo-delete']) && ( $_POST['yoast_seo-delete'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_yoast_seo_delete'];
            if (!wp_verify_nonce($nonce, RTP_YOAST_SEO . '-delete')) {
                add_settings_error('delete-plugin', 'failure_plugin_deletion', __('You do not have sufficient permissions to delete this plugin.', 'rtPanel'));
            } else {
                delete_plugins(array(RTP_YOAST_SEO));
                add_settings_error('delete-plugin', 'plugin_deletion', __('Yoast WordPress SEO Plugin has been Deleted.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['regenerate-activate']) && ( $_POST['regenerate-activate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_regenerate_activate'];
            if (!wp_verify_nonce($nonce, RTP_REGENERATE_THUMBNAILS . '-activate')) {
                add_settings_error('activate-plugin', 'failure_plugin_activation', __('You do not have sufficient permissions to activate this plugin.', 'rtPanel'));
            } else {
                activate_plugin(RTP_REGENERATE_THUMBNAILS);
                add_settings_error('activate-plugin', 'plugin_activation', __('Regenerate Thumbnails Plugin has been Activated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['regenerate-deactivate']) && ( $_POST['regenerate-deactivate'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_regenerate_deactivate'];
            if (!wp_verify_nonce($nonce, RTP_REGENERATE_THUMBNAILS . '-deactivate')) {
                add_settings_error('deactivate-plugin', 'failure_plugin_deactivation', __('You do not have sufficient permissions to deactivate this plugin.', 'rtPanel'));
            } else {
                deactivate_plugins(array(RTP_REGENERATE_THUMBNAILS));
                add_settings_error('deactivate-plugin', 'plugin_deactivation', __('Regenerate Thumbnails Plugin has been Deactivated.', 'rtPanel'), 'updated');
            }
        } elseif (isset($_POST['regenerate-delete']) && ( $_POST['regenerate-delete'] == 1 )) {
            $nonce = $_REQUEST['_wpnonce_regenerate_delete'];
            if (!wp_verify_nonce($nonce, RTP_REGENERATE_THUMBNAILS . '-delete')) {
                add_settings_error('delete-plugin', 'failure_plugin_deletion', __('You do not have sufficient permissions to delete this plugin.', 'rtPanel'));
            } else {
                delete_plugins(array(RTP_REGENERATE_THUMBNAILS));
                add_settings_error('delete-plugin', 'plugin_deletion', __('Regenerate Thumbnails Plugin has been Deleted.', 'rtPanel'), 'updated');
            }
        }
    } elseif (isset($_POST['rtp_logo_favicon_reset'])) {
        $options = maybe_unserialize($rtp_general);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['logo_use'] = $default[0]['logo_use'];
        $input['logo_upload'] = $default[0]['logo_upload'];
        $input['logo_id'] = $default[0]['logo_id'];
        $input['logo_width'] = $default[0]['logo_width'];
        $input['logo_height'] = $default[0]['logo_height'];
        $input['login_head'] = $default[0]['login_head'];
        $input['favicon_use'] = $default[0]['favicon_use'];
        $input['favicon_upload'] = $default[0]['favicon_upload'];
        $input['favicon_id'] = $default[0]['favicon_id'];
        add_settings_error('logo_favicon_settings', 'logo_favicon_reset', __('The Logo Settings have been restored to Default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_fb_ogp_reset'])) {
        $options = maybe_unserialize($rtp_general);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['fb_app_id'] = $default[0]['fb_app_id'];
        $input['fb_admins'] = $default[0]['fb_admins'];
        add_settings_error('facebook_ogp', 'reset_facebook_ogp', __('The Facebook Open Graph Settings have been restored to Default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_feed_reset'])) {
        $options = maybe_unserialize($rtp_general);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['feedburner_url'] = $default[0]['feedburner_url'];
        add_settings_error('feedburner_url', 'reset_feeburner_url', __('The Feedburner Settings have been restored to Default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_google_reset'])) {
        $options = maybe_unserialize($rtp_general);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['search_code'] = $default[0]['search_code'];
        $input['search_layout'] = $default[0]['search_layout'];
        add_settings_error('search_code', 'reset_search_code', __('The Google Custom Search Integration has been restored to Default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_sidebar_reset'])) {
        $options = maybe_unserialize($rtp_general);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['footer_sidebar'] = $default[0]['footer_sidebar'];
        $input['buddypress_sidebar'] = $default[0]['buddypress_sidebar'];
        $input['bbpress_sidebar'] = $default[0]['bbpress_sidebar'];
        add_settings_error('sidebar', 'reset_sidebar', __('The Sidebar Settings have been restored to Default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_custom_styles_reset'])) {
        $options = maybe_unserialize($rtp_general);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['custom_styles'] = $default[0]['custom_styles'];
        add_settings_error('custom_styles', 'reset_custom_styles', __('Custom Styles has been restored to Default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_export'])) {
        rtp_export();
        die();
    } elseif (isset($_POST['rtp_import'])) {
        $general = rtp_import($_FILES['rtp_import']);
        if ($general && $general != 'ext') {
            unset($input);
            $input = maybe_unserialize($general);
            add_settings_error('rtp_import', 'import', __('rtPanel Options have been imported successfully', 'rtPanel'), 'updated');
        } elseif ($general == 'ext') {
            add_settings_error('rtp_import', 'no_import', __('Not a valid RTP file', 'rtPanel'));
        } else {
            add_settings_error('rtp_import', 'no_import', __('The file is corrupt. There was an error while importing. Please Try Again', 'rtPanel'));
        }
    } elseif (isset($_POST['rtp_reset'])) {
        $input = $default[0];
        add_settings_error('rtp_general', 'reset_general_options', __('All the rtPanel General Settings have been restored to default.', 'rtPanel'), 'updated');
    }
    return $input; // Return validated input.
}

/**
 * Data validation for rtPanel Post & Comments Options
 * 
 * @uses $rtp_post_comments array
 * @param array $input all post & comments options inputs.
 * @return Array
 *
 * @since rtPanel 2.0
 */
function rtp_post_comments_validate($input) {
    global $rtp_post_comments;
    $default = rtp_theme_setup_values();

    if (isset($_POST['rtp_submit'])) {
        $input['notices'] = $rtp_post_comments['notices'];
        if ($input['summary_show']) {
            $updated = 0;
            if (trim($input['read_text']) != $rtp_post_comments['read_text']) {
                $input['read_text'] = trim($input['read_text']);
                $updated++;
            }
            if (!preg_match('/^[0-9]{1,3}$/i', $input['word_limit'])) {
                $input['word_limit'] = $rtp_post_comments['word_limit'];
                add_settings_error('word_limit', 'invalid_word_limit', __('The Word Limit provided is invalid. Please provide a proper value.', 'rtPanel'));
            } elseif (trim($input['word_limit']) != $rtp_post_comments['word_limit']) {
                $updated++;
            }
            if ($updated) {
                add_settings_error('post_summary_settings', 'post_summary_settings', __('The Post Summary Settings have been updated.', 'rtPanel'), 'updated');
            }
            if ($input['thumbnail_show']) {
                $updated = 0;
                if (!preg_match('/^[0-9]{1,3}$/i', $input['thumbnail_width'])) {
                    $input['thumbnail_width'] = get_option('thumbnail_size_w');
                    add_settings_error('thumbnail_width', 'invalid_thumbnail_width', __('The Thumbnail Width provided is invalid. Please provide a proper value.', 'rtPanel'));
                } elseif (get_option('thumbnail_size_w') != $input['thumbnail_width']) {
                    $input['notices'] = '1';
                    update_option('thumbnail_size_w', $input['thumbnail_width']);
                    $updated++;
                }

                if (!preg_match('/^[0-9]{1,3}$/i', $input['thumbnail_height'])) {
                    $input['thumbnail_height'] = get_option('thumbnail_size_h');
                    add_settings_error('thumbnail_height', 'invalid_thumbnail_height', __('The Thumbnail Height provided is invalid. Please provide a proper value.', 'rtPanel'));
                } elseif (get_option('thumbnail_size_h') != $input['thumbnail_height']) {
                    $input['notices'] = '1';
                    update_option('thumbnail_size_h', $input['thumbnail_height']);
                    $updated++;
                }

                if ($input['thumbnail_crop'] != get_option('thumbnail_crop')) {
                    $input['notices'] = '1';
                    update_option('thumbnail_crop', $input['thumbnail_crop']);
                    $updated++;
                }
                if ($updated) {
                    add_settings_error('post_thumbnail_settings', 'post_thumbnail_settings', __('The Post Thumbnail Settings have been updated', 'rtPanel'), 'updated');
                }
            } else {
                $input['thumbnail_position'] = $rtp_post_comments['thumbnail_position'];
                $input['thumbnail_frame'] = $rtp_post_comments['thumbnail_frame'];
            }
        } else {
            $input['thumbnail_show'] = $rtp_post_comments['thumbnail_show'];
            $input['word_limit'] = $rtp_post_comments['word_limit'];
            $input['read_text'] = $rtp_post_comments['read_text'];
            $input['thumbnail_position'] = $rtp_post_comments['thumbnail_position'];
            $input['thumbnail_frame'] = $rtp_post_comments['thumbnail_frame'];
        }

        if (!in_array($input['post_date_format_u'], array($rtp_post_comments['post_date_format_u'], 'F j, Y', 'Y/m/d', 'm/d/Y', 'd/m/Y'))) {
            $input['post_date_format_u'] = str_replace('<', '', $input['post_date_format_u']);
            $input['post_date_format_l'] = str_replace('<', '', $input['post_date_format_l']);
            $input['post_date_custom_format_u'] = str_replace('<', '', $input['post_date_custom_format_u']);
            $input['post_date_custom_format_l'] = str_replace('<', '', $input['post_date_custom_format_l']);
        }

        if (!$input['post_date_u']) {
            $input['post_date_format_u'] = $rtp_post_comments['post_date_format_u'];
            $input['post_date_custom_format_u'] = $rtp_post_comments['post_date_custom_format_u'];
        }

        if (!$input['post_date_l']) {
            $input['post_date_format_l'] = $rtp_post_comments['post_date_format_l'];
            $input['post_date_custom_format_l'] = $rtp_post_comments['post_date_custom_format_l'];
        }

        if (!$input['post_author_u']) {
            $input['author_count_u'] = $rtp_post_comments['author_count_u'];
            $input['author_link_u'] = $rtp_post_comments['author_link_u'];
        }

        if (!$input['post_author_l']) {
            $input['author_count_l'] = $rtp_post_comments['author_count_l'];
            $input['author_link_l'] = $rtp_post_comments['author_link_l'];
        }

        if ($input['pagination_show']) {
            $updated = 0;
            if (trim($input['prev_text']) != $rtp_post_comments['prev_text']) {
                $input['prev_text'] = trim($input['prev_text']);
                $updated++;
            }
            if (trim($input['next_text']) != $rtp_post_comments['next_text']) {
                $input['next_text'] = trim($input['next_text']);
                $updated++;
            }
            if (!preg_match('/^[0-9]{1,3}$/i', $input['end_size'])) {
                $input['end_size'] = $rtp_post_comments['end_size'];
                add_settings_error('end_size', 'invalid_end_size', __('The End Size provided is invalid. Please provide a proper value.', 'rtPanel'));
            }
            if (!preg_match('/^[0-9]{1,3}$/i', $input['mid_size'])) {
                $input['mid_size'] = $rtp_post_comments['mid_size'];
                add_settings_error('mid_size', 'invalid_mid_size', __('The Mid Size provided is invalid. Please provide a proper value.', 'rtPanel'));
            }
            if ($updated) {
                add_settings_error('pagination_settings', 'pagination_settings', __('The Pagination Settings have been updated.', 'rtPanel'), 'updated');
            }
        } else {
            $input['prev_text'] = $rtp_post_comments['prev_text'];
            $input['next_text'] = $rtp_post_comments['next_text'];
            $input['end_size'] = $rtp_post_comments['end_size'];
            $input['mid_size'] = $rtp_post_comments['mid_size'];
        }

        if (!$input['gravatar_show']) {
            $input['gravatar_size'] = $rtp_post_comments['gravatar_size'];
        }
    } elseif (isset($_POST['rtp_summary_reset'])) {
        $options = maybe_unserialize($rtp_post_comments);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['notices'] = $rtp_post_comments['notices'];
        $input['summary_show'] = $default[1]['summary_show'];
        $input['word_limit'] = $default[1]['word_limit'];
        $input['read_text'] = $default[1]['read_text'];
        add_settings_error('summary', 'reset_summary', __('The Post Summary Settings have been restored to default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_thumbnail_reset'])) {
        $options = maybe_unserialize($rtp_post_comments);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['notices'] = $rtp_post_comments['notices'];
        $input['thumbnail_show'] = $default[1]['thumbnail_show'];
        $input['thumbnail_position'] = $default[1]['thumbnail_position'];
        $input['thumbnail_frame'] = $default[1]['thumbnail_frame'];
        add_settings_error('thumbnail', 'reset_thumbnail', __('The Post Thumbnail Settings have been restored to default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_meta_reset'])) {
        $options = maybe_unserialize($rtp_post_comments);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['notices'] = $rtp_post_comments['notices'];
        $input['post_date_u'] = $default[1]['post_date_u'];
        $input['post_date_format_u'] = $default[1]['post_date_format_u'];
        $input['post_date_custom_format_u'] = $default[1]['post_date_custom_format_u'];
        $input['post_author_u'] = $default[1]['post_author_u'];
        $input['author_count_u'] = $default[1]['author_count_u'];
        $input['author_link_u'] = $default[1]['author_link_u'];
        $input['post_category_u'] = $default[1]['post_category_u'];
        $input['post_tags_u'] = $default[1]['post_tags_u'];
        $input['post_date_l'] = $default[1]['post_date_l'];
        $input['post_date_format_l'] = $default[1]['post_date_format_l'];
        $input['post_date_custom_format_l'] = $default[1]['post_date_custom_format_l'];
        $input['post_author_l'] = $default[1]['post_author_l'];
        $input['author_count_l'] = $default[1]['author_count_l'];
        $input['author_link_l'] = $default[1]['author_link_l'];
        $input['post_category_l'] = $default[1]['post_category_l'];
        $input['post_tags_l'] = $default[1]['post_tags_l'];
        $args = array('_builtin' => false);
        $taxonomies = get_taxonomies($args, 'names');

        if (!empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $input['post_' . $taxonomy . '_u'] = '0';
                $input['post_' . $taxonomy . '_l'] = '0';
            }
        }
        add_settings_error('post_meta', 'reset_post_meta', __('The Post Meta Settings have been restored to default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_pagination_reset'])) {
        $options = maybe_unserialize($rtp_post_comments);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['notices'] = $rtp_post_comments['notices'];
        $input['pagination_show'] = $default[1]['pagination_show'];
        $input['prev_text'] = $default[1]['prev_text'];
        $input['next_text'] = $default[1]['next_text'];
        $input['end_size'] = $default[1]['end_size'];
        $input['mid_size'] = $default[1]['mid_size'];
        add_settings_error('pagination', 'reset_pagination', __('The Pagination Settings have been restored to default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_comment_reset'])) {
        $options = maybe_unserialize($rtp_post_comments);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['notices'] = $rtp_post_comments['notices'];
        $input['compact_form'] = $default[1]['compact_form'];
        $input['hide_labels'] = $default[1]['hide_labels'];
        $input['comment_textarea'] = $default[1]['comment_textarea'];
        $input['comment_separate'] = $default[1]['comment_separate'];
        add_settings_error('comment', 'reset_comment', __('The Comment Form Settings have been restored to default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_gravatar_reset'])) {
        $options = maybe_unserialize($rtp_post_comments);
        unset($input);

        foreach ($options as $option => $value)
            $input[$option] = $value;

        $input['notices'] = $rtp_post_comments['notices'];
        $input['gravatar_show'] = $default[1]['gravatar_show'];
        $input['gravatar_size'] = $default[1]['gravatar_size'];
        add_settings_error('gravatar', 'reset_gravatar', __('The Gravatar Settings have been restored to default.', 'rtPanel'), 'updated');
    } elseif (isset($_POST['rtp_reset'])) {
        $input = $default[1];
        $input['notices'] = $rtp_post_comments['notices'];
        $args = array('_builtin' => false);
        $taxonomies = get_taxonomies($args, 'names');
        if (!empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $input['post_' . $taxonomy . '_u'] = '0';
                $input['post_' . $taxonomy . '_l'] = '0';
            }
        }
        add_settings_error('rtp_post_comments', 'reset_post_comments_options', __('All the rtPanel Post & Comments Settings have been restored to default.', 'rtPanel'), 'updated');
    }
    return $input; // return validated input
}

/**
 * Setup Default Values for rtPanel
 *
 * This function sets up default values for 'rtPanel' and creates
 * 2 options in the WordPress options table: 'rtp_general' &
 * 'rtp_post_comments', where the values for the 'General' and
 * 'Post & Comments' tabs are stored respectively
 *
 * @return array.
 *
 * @since rtPanel 2.0
 */
function rtp_theme_setup_values() {
    global $rtp_general, $rtp_post_comments, $rtp_version;

    $default_general = array(
        'logo_use' => 'image',
        'logo_upload' => RTP_IMG_FOLDER_URL . '/rtp-logo.jpg',
        'logo_id' => 0,
        'logo_width' => 224,
        'logo_height' => 51,
        'login_head' => '0',
        'favicon_use' => 'image',
        'favicon_upload' => RTP_IMG_FOLDER_URL . '/favicon.ico',
        'favicon_id' => 0,
        'fb_app_id' => '',
        'fb_admins' => '',
        'feedburner_url' => '',
        'footer_sidebar' => '1',
        'buddypress_sidebar' => 'default-sidebar',
        'bbpress_sidebar' => 'default-sidebar',
        'custom_styles' => '',
        'search_code' => '',
        'search_layout' => '1',
    );

    $default_post_comments = array(
        'notices' => isset($rtp_post_comments['notices']) ? $rtp_post_comments['notices'] : 0,
        'summary_show' => '1',
        'word_limit' => 55,
        'read_text' => __('Read More&hellip;', 'rtPanel'),
        'thumbnail_show' => '1',
        'thumbnail_position' => 'Right',
        'thumbnail_width' => get_option('thumbnail_size_w'),
        'thumbnail_height' => get_option('thumbnail_size_h'),
        'thumbnail_crop' => get_option('thumbnail_crop'),
        'thumbnail_frame' => '0',
        'post_date_u' => '1',
        'post_date_format_u' => 'F j, Y',
        'post_date_custom_format_u' => 'F j, Y',
        'post_author_u' => '1',
        'author_count_u' => '0',
        'author_link_u' => '1',
        'post_category_u' => '1',
        'post_tags_u' => '0',
        'post_date_l' => '0',
        'post_date_format_l' => 'F j, Y',
        'post_date_custom_format_l' => 'F j, Y',
        'post_author_l' => '0',
        'author_count_l' => '0',
        'author_link_l' => '1',
        'post_category_l' => '0',
        'post_tags_l' => '0',
        'pagination_show' => '1',
        'prev_text' => '&laquo; Previous',
        'next_text' => 'Next &raquo;',
        'end_size' => '1',
        'mid_size' => '2',
        'compact_form' => '1',
        'hide_labels' => '1',
        'comment_textarea' => '0',
        'comment_separate' => '1',
        'attachment_comments' => '0',
        'gravatar_show' => '1',
        'gravatar_size' => '64',
    );

    $args = array('_builtin' => false);
    $taxonomies = get_taxonomies($args, 'names');
    if (!empty($taxonomies)) {
        foreach ($taxonomies as $taxonomy) {
            $default_post_comments['post_' . $taxonomy . '_u'] = '0';
            $default_post_comments['post_' . $taxonomy . '_l'] = '0';
        }
    }

    if (!get_option('rtp_general')) {
        update_option('rtp_general', $default_general);
        $blog_users = get_users();

        foreach ($blog_users as $blog_user) {
            $blog_user_id = $blog_user->ID;
            if (!get_user_meta($blog_user_id, 'screen_layout_appearance_page_rtp_general'))
                update_user_meta($blog_user_id, 'screen_layout_appearance_page_rtp_general', 1, NULL);
        }
    }
    if (!get_option('rtp_post_comments')) {
        update_option('rtp_post_comments', $default_post_comments);
        $blog_users = get_users();

        foreach ($blog_users as $blog_user) {
            $blog_user_id = $blog_user->ID;
            if (!get_user_meta($blog_user_id, 'screen_layout_appearance_page_rtp_post_comments'))
                update_user_meta($blog_user_id, 'screen_layout_appearance_page_rtp_post_comments', 1, NULL);
        }
    }

    $rtp_version = rtp_export_version();
    if (!get_option('rtp_version') || ( get_option('rtp_version') != $rtp_version )) {
        update_option('rtp_version', $rtp_version);
        $updated_general = wp_parse_args($rtp_general, $default_general);
        $updated_post_comments = wp_parse_args($rtp_post_comments, $default_post_comments);
        update_option('rtp_general', $updated_general);
        update_option('rtp_post_comments', $updated_post_comments);
    }

    return array($default_general, $default_post_comments);
}

// Redirect to rtPanel on theme activation //
function rtp_theme_activation($themename, $theme = false) {
    global $rtp_general;
    $update = 0;
    if (isset($rtp_general['logo_show']) && $rtp_general['logo_show']) {
        $update++;
        $rtp_general['logo_use'] = 'image';
        unset($rtp_general['logo_show']);
    } elseif (isset($rtp_general['logo_show'])) {
        $update++;
        $rtp_general['logo_use'] = 'site_title';
        unset($rtp_general['logo_show']);
    }
    if (isset($rtp_general['use_logo']) && ( $rtp_general['logo_use'] == 'use_logo_url' )) {
        $update++;
        $rtp_general['logo_upload'] = $rtp_general['logo_url'];
        $id = rtp_get_attachment_id_from_src($rtp_general['logo_upload'], true);
        $img_dimensions = rtp_get_image_dimensions($rtp_general['logo_upload'], true, '', $id);
        $rtp_general['logo_id'] = $id;
        $rtp_general['logo_width'] = $img_dimensions['width'];
        $rtp_general['logo_height'] = $img_dimensions['height'];
        unset($rtp_general['use_logo']);
        unset($rtp_general['logo_url']);
    } elseif (isset($rtp_general['use_logo']) && ( $rtp_general['use_logo'] == 'use_logo_upload' )) {
        $update++;
        $id = rtp_get_attachment_id_from_src($rtp_general['logo_upload'], true);
        $img_dimensions = rtp_get_image_dimensions($rtp_general['logo_upload'], true, '', $id);
        $rtp_general['logo_id'] = $id;
        $rtp_general['logo_width'] = $img_dimensions['width'];
        $rtp_general['logo_height'] = $img_dimensions['height'];
        unset($rtp_general['use_logo']);
    }
    if (isset($rtp_general['favicon_show']) && $rtp_general['favicon_show']) {
        $update++;
        $rtp_general['favicon_use'] = 'image';
        unset($rtp_general['favicon_show']);
    } elseif (isset($rtp_general['favicon_show'])) {
        $update++;
        $rtp_general['favicon_use'] = 'disable';
        unset($rtp_general['favicon_show']);
    }
    if (isset($rtp_general['use_favicon']) && ( $rtp_general['use_favicon'] == 'use_favicon_url' )) {
        $update++;
        $rtp_general['favicon_upload'] = $rtp_general['favicon_url'];
        $id = rtp_get_attachment_id_from_src($rtp_general['favicon_upload'], true);
        $img_dimensions = rtp_get_image_dimensions($rtp_general['favicon_upload'], true, '', $id);
        $rtp_general['favicon_id'] = $id;
        unset($rtp_general['use_favicon']);
    } elseif (isset($rtp_general['use_favicon']) && ( $rtp_general['use_favicon'] == 'use_favicon_upload' )) {
        $update++;
        $rtp_general['favicon_id'] = rtp_get_attachment_id_from_src($rtp_general['favicon_upload'], true);
        unset($rtp_general['use_favicon']);
        unset($rtp_general['favicon_url']);
    }
    if ($update) {
        update_option('rtp_general', $rtp_general);
    }
}

add_action('after_switch_theme', 'rtp_theme_activation', '', 2);

/**
 * Feedburner Redirection Code
 *
 * @uses string $feed
 * @uses array $rtp_general
 *
 * @since rtPanel 2.0
 */
function rtp_feed_redirect() {
    global $feed, $rtp_general, $withcomments;
    if (is_feed() && $feed != 'comments-rss2' && ( $withcomments != 1 ) && !is_singular() && !is_archive() && !empty($rtp_general['feedburner_url'])) {
        if (function_exists('status_header')) {
            status_header(302);
        }
        header('Location: ' . trim($rtp_general['feedburner_url']));
        header('HTTP/1.1 302 Temporary Redirect');
        exit();
    }
}

/**
 * Used to check the feed type ( default or comment feed )
 *
 * @uses $rtp_general array
 *
 * @since rtPanel 2.0
 */
function rtp_check_url() {
    global $rtp_general;
    switch (basename($_SERVER['PHP_SELF'])) {
        case 'wp-rss.php' :
        case 'wp-rss2.php' :
        case 'wp-atom.php' :
        case 'wp-rdf.php' : if (trim($rtp_general['feedburner_url']) != '') {
                if (function_exists('status_header')) {
                    status_header(302);
                }
                header('Location: ' . trim($rtp_general['feedburner_url']));
                header('HTTP/1.1 302 Temporary Redirect');
                exit();
            }
            break;

        case 'wp-commentsrss2.php': break;
    }
}

/* Condition to redirect WordPress feeds to feed burner */
if (isset($_SERVER['HTTP_USER_AGENT']) && !preg_match('/feedburner|feedvalidator/i', $_SERVER['HTTP_USER_AGENT'])) {
    add_action('template_redirect', 'rtp_feed_redirect');
    add_action('init', 'rtp_check_url');
}

/* condition to check Admin Login Logo option */
if (isset($rtp_general['logo_use']) && isset($rtp_general['login_head']) && ( 'image' == $rtp_general['logo_use'] ) && $rtp_general['login_head']) {
    add_action('login_head', 'rtp_custom_login_logo');
    add_filter('login_headerurl', 'rtp_login_site_url');
}

/**
 * Dislays custom logo on Login Page
 *
 * @uses $rtp_general array
 *
 * @since rtPanel 2.0
 */
function rtp_custom_login_logo() {
    global $rtp_general;
    $custom_logo = $rtp_general['logo_upload'];
    if (isset($rtp_general['logo_width']) && !empty($rtp_general['logo_width']) && isset($rtp_general['logo_height']) && !empty($rtp_general['logo_height'])) {
        $rtp_logo_width = $rtp_general['logo_width'];
        $rtp_logo_height = $rtp_general['logo_height'];
    } else {
        $dimensions = rtp_get_image_dimensions($custom_logo, true);
        if (isset($dimensions['width']) && isset($dimensions['height'])) {
            $rtp_logo_width = $dimensions['width'];
            $rtp_logo_height = $dimensions['height'];
        } else {
            $rtp_logo_width = $rtp_logo_height = 0;
        }
    }
    $rtp_wp_loginbox_width = 312;
    if ($rtp_logo_width > $rtp_wp_loginbox_width) {
        $ratio = $rtp_logo_height / $rtp_logo_width;
        $rtp_logo_height = ceil($ratio * $rtp_wp_loginbox_width);
        $rtp_logo_width = $rtp_wp_loginbox_width;
        $rtp_background_size = 'contain';
    } else {
        $rtp_background_size = 'auto';
    }

    echo '<style type="text/css">
        .login h1 { margin-left: 8px; }
        .login h1 a { background: url(' . $custom_logo . ') no-repeat 50% 0;
                background-size: ' . $rtp_background_size . ';';
    if ($rtp_logo_width && $rtp_logo_height) {
        echo 'height: ' . $rtp_logo_height . 'px;
              width: ' . $rtp_logo_width . 'px; margin: 0 auto 15px; padding: 0; }';
    }
    echo '</style>';
}

/**
 * Returns Home URL, to be used by custom logo
 * 
 * @return string
 *
 * @since rtPanel 2.0
 */
function rtp_login_site_url() {
    return home_url('/');
}

/**
 * Default rtPanel admin sidebar with metabox styling
 *
 * @return rtPanel_admin_sidebar
 *
 * @since rtPanel 2.0
 */
function rtp_default_admin_sidebar() {
    ?>
    <div class="postbox" id="social">
        <div title="<?php _e('Click to toggle', 'rtPanel'); ?>" class="handlediv"><br /></div>
        <h3 class="hndle"><span><?php _e('Getting Social is Good', 'rtPanel'); ?></span></h3>
        <div class="inside" style="text-align:center;">
            <a href="http://www.facebook.com/rtPanel" target="_blank" title="<?php _e('Become a fan on Facebook', 'rtPanel'); ?>" class="rtpanel-facebook"><?php _e('Facebook', 'rtPanel'); ?></a>
            <a href="http://twitter.com/rtPanel" target="_blank" title="<?php _e('Follow us on Twitter', 'rtPanel'); ?>" class="rtpanel-twitter"><?php _e('Twitter', 'rtPanel'); ?></a>
            <a href="http://feeds.feedburner.com/rtpanel" target="_blank" title="<?php _e('Subscribe to our feeds', 'rtPanel'); ?>" class="rtpanel-rss"><?php _e('RSS Feed', 'rtPanel'); ?></a>
        </div>
    </div>

    <div class="postbox" id="donations">
        <div title="<?php _e('Click to toggle', 'rtPanel'); ?>" class="handlediv"><br /></div>
        <h3 class="hndle"><span><?php _e('Promote, Donate, Share', 'rtPanel'); ?>...</span></h3>
        <div class="inside">
            <p><?php _e('Buy coffee/beer for team behind <a href="http://rtcamp.com/rtpanel/" title="rtPanel">rtPanel</a>.', 'rtPanel'); ?></p>
            <div class="rt-paypal" style="text-align:center">
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                    <input type="hidden" name="cmd" value="_donations" />
                    <input type="hidden" name="business" value="paypal@rtcamp.com" />
                    <input type="hidden" name="lc" value="US" />
                    <input type="hidden" name="item_name" value="rtPanel" />
                    <input type="hidden" name="no_note" value="0" />
                    <input type="hidden" name="currency_code" value="USD" />
                    <input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest" />
                    <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" name="submit" alt="PayPal - The safer, easier way to pay online!" />
                    <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
                </form>
            </div>
            <div class="rt-social-share">
                <div class="rt-twitter rtp-social-box">
                    <a href="http://twitter.com/share"  class="twitter-share-button" data-text="I &hearts; #rtPanel"  data-url="http://rtcamp.com/rtpanel/" data-count="vertical" data-via="rtPanel"><?php _e('Tweet', 'rtPanel'); ?></a>
                </div>
                <div class="rt-facebook rtp-social-box">
                    <a style=" text-align:center;" name="fb_share" type="box_count" share_url="http://rtpanel.com/"></a>
                </div>
                <div class="clear"></div>
            </div>
        </div>
    </div>

    <div class="postbox" id="support">
        <div title="<?php _e('Click to toggle', 'rtPanel'); ?>" class="handlediv"><br /></div>
        <h3 class="hndle"><span><?php _e('Free Support', 'rtPanel'); ?></span></h3>
        <div class="inside"><p><?php _e(' If you are facing any problems while using rtPanel, or have good ideas for improvements, please discuss the same in our <a href="http://rtcamp.com/support/forum/rtpanel/" target="_blank" title="Click here for rtPanel Free Support">Support forums</a>', 'rtPanel'); ?>.</p></div>
    </div>

    <div class="postbox" id="latest_news">
        <div title="<?php _e('Click to toggle', 'rtPanel'); ?>" class="handlediv"><br /></div>
        <h3 class="hndle"><span><?php _e('Latest News', 'rtPanel'); ?></span></h3>
        <div class="inside"><?php rtp_get_feeds(); ?></div>
    </div><?php
}

/**
 * Display feeds from a specified Feed URL
 *
 * @param string $feed_url The Feed URL.
 *
 * @since rtPanel 2.0
 */
function rtp_get_feeds($feed_url = 'http://rtcamp.com/blog/category/rtpanel/feed/') {

// Get RSS Feed(s)
    require_once( ABSPATH . WPINC . '/feed.php' );
    $maxitems = 0;
// Get a SimplePie feed object from the specified feed source.
    $rss = fetch_feed($feed_url);
    if (!is_wp_error($rss)) { // Checks that the object is created correctly
// Figure out how many total items there are, but limit it to 5.
        $maxitems = $rss->get_item_quantity(5);

// Build an array of all the items, starting with element 0 (first element).
        $rss_items = $rss->get_items(0, $maxitems);
    }
    ?>
    <ul><?php
    if ($maxitems == 0) {
        echo '<li>' . __('No items', 'rtPanel') . '.</li>';
    } else {
// Loop through each feed item and display each item as a hyperlink.
        foreach ($rss_items as $item) {
            ?>
                <li>
                    <a href='<?php echo $item->get_permalink(); ?>' title='<?php echo __('Posted ', 'rtPanel') . $item->get_date('j F Y | g:i a'); ?>'><?php echo $item->get_title(); ?></a>
                </li><?php
        }
    }
    ?>
    </ul><?php
}

/**
 * Adds rtPanel Contextual help
 *
 * @return string
 *
 * @since rtPanel 2.1
 */
function rtp_theme_options_help() {

    $general_help = '<p>';
    $general_help .= __('rtPanel is the most easy to use WordPress Theme Framework. You will find many state of the art options and widgets with rtPanel.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('rtPanel framework is used worldwide and keeping this in mind we have made it localization ready. ', 'rtPanel');
    $general_help .= __('Developers can use rtPanel as a basic and stripped to bones theme framework for developing their own creative and wonderful WordPress Themes.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('By using rtPanel, developers and users can specify settings for basic functions (like date format, excerpt word count etc.) directly from theme options. ', 'rtPanel');
    $general_help .= __('rtPanel provides theme options to manage some basic settings for your theme. ', 'rtPanel');
    $general_help .= __('Below are the options provided for your convenience.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Logo Settings:</strong> Theme\'s logo can be managed from this setting.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Favicon Settings:</strong> Theme\'s favicon can be managed from this setting.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Facebook Open Graph Settings:</strong> This setting will provide an option to specify Faceboook App ID/Admin ID(s), required for Open Graph.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>FeedBurner Settings:</strong> FeedBurner URL can be specified from this setting to redirect your feeds.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Google Custom Search Integration:</strong> This option would enable you to harness the power of Google Search instead of the default WordPress search by specifying the Google Custom Search Code.  You also have the option of rendering the Google Search Page without the sidebar.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Sidebar Settings:</strong> Enable / Disable the Footer Sidebar from here.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Custom Styles:</strong> You can specify your own CSS styles in this option to override the default Style.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Plugin Support:</strong> You will get a summary of plugins\' status that are supported by rtPanel. This information box will allow you to manipulate the plugin settings on the fly.', 'rtPanel');
    $general_help .= '</p><p>';
    $general_help .= __('<strong>Backup rtPanel Options:</strong> Export or import all settings that you have configured in rtPanel.', 'rtPanel');
    $general_help .= '</p>';
    $general_help .= '<p>' . __('Remember to click "<strong>Save All Changes</strong>" to save any changes you have made to the theme options.', 'rtPanel') . '</p>';

    $post_comment_help = '<p>';
    $post_comment_help .= __('rtPanel is the most easy to use WordPress Theme Framework. You will find many state of the art options and widgets with rtPanel.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('rtPanel framework is used worldwide and keeping this in mind we have made it localization ready. ', 'rtPanel');
    $post_comment_help .= __('Developers can use rtPanel as a basic and stripped to bones theme framework for developing their own creative and wonderful WordPress Themes.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('By using rtPanel, developers and users can specify settings for basic functions (like date format, excerpt word count etc.) directly from theme options. ', 'rtPanel');
    $post_comment_help .= __('rtPanel provides theme options to manage some basic settings for your theme. ', 'rtPanel');
    $post_comment_help .= __('Below are the options provided for your convenience.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('<strong>Post Summaries Settings:</strong> Specify the different excerpt parameters like word count etc.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('<strong>Post Thumbnail Settings:</strong> Specify the post thumbnail options like position, size etc.', 'rtPanel');
    $post_comment_help .= '<br />';
    $post_comment_help .= __('<small><strong><em>NOTE:</em></strong> If you are using this option to change height or width of the thumbnail, then please use \'Regenerate Thumbnails\' plugin, to apply the new dimension settings to your thumbnails.</small>', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('<strong>Post Meta Settings:</strong> You can specify the post meta options like post date format, display or hide author name and their positions in relation with the content.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('<strong>Pagination Settings:</strong> Enable this setting to use default WordPress pagination.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('<strong>Comment Form Settings:</strong> You can specify the comment form settings from this option.', 'rtPanel');
    $post_comment_help .= '</p><p>';
    $post_comment_help .= __('<strong>Gravtar Settings:</strong> Specify the general Gravtar support from this option.', 'rtPanel');
    $post_comment_help .= '</p>';
    $post_comment_help .= '<p>' . __('Remember to click "<strong>Save All Changes</strong>" to save any changes you have made to the theme options.', 'rtPanel') . '</p>';

    $sidebar = '<p><strong>' . __('For more information, <br />you can always visit:', 'rtPanel') . '</strong></p>' .
            '<p>' . __('<a href="http://rtcamp.com/rtpanel/" target="_blank" title="rtPanel Official Page">rtPanel Official Page</a>', 'rtPanel') . '</p>' .
            '<p>' . __('<a href="http://rtcamp.com/rtpanel/docs/" target="_blank" title="rtPanel Documentation">rtPanel Documentation</a>', 'rtPanel') . '</p>' .
            '<p>' . __('<a href="http://rtcamp.com/support/forum/rtpanel/" target="_blank" title="rtPanel Forum">rtPanel Forum</a>', 'rtPanel') . '</p>';

    $screen = get_current_screen();
    $screen->add_help_tab(array('title' => __('General', 'rtPanel'), 'id' => 'rtp-general-help', 'content' => $general_help));
    $screen->add_help_tab(array('title' => __('Post &amp; Comment', 'rtPanel'), 'id' => 'post-comments-help', 'content' => $post_comment_help));
    $screen->set_help_sidebar($sidebar);
}

add_action('load-appearance_page_rtp_general', 'rtp_theme_options_help');
add_action('load-appearance_page_rtp_post_comments', 'rtp_theme_options_help');

/**
 * Show rtPanel only to Admin Users ( Admin-Bar only !!! )
 *
 * @since rtPanel 2.0
 */
function rtp_admin_bar_init() {
    // Is the user sufficiently leveled, or has the bar been disabled?
    if (!is_super_admin() || !is_admin_bar_showing()) {
        return;
    }
    // Good to go, let's do this!
    add_action('admin_bar_menu', 'rtp_admin_bar_links', 500);
}

add_action('admin_bar_init', 'rtp_admin_bar_init');

/**
 * Adds rtPanel links to Admin Bar
 *
 * @uses object $wp_admin_bar
 *
 * @since rtPanel 2.0
 */
function rtp_admin_bar_links() {
    global $wp_admin_bar, $rt_panel_theme;

    // Links to add, in the form: 'Label' => 'URL'
    foreach ($rt_panel_theme->theme_pages as $key => $theme_page) {
        if (is_array($theme_page))
            $links[$theme_page['menu_title']] = array('url' => admin_url('themes.php?page=' . $theme_page['menu_slug']), 'slug' => $theme_page['menu_slug']);
    }

    //  Add parent link
    $wp_admin_bar->add_menu(array(
        'title' => 'rtPanel',
        'href' => admin_url('themes.php?page=rtp_general'),
        'id' => 'rt_links',
    ));

    // Add submenu links
    foreach ($links as $label => $menu) {
        $wp_admin_bar->add_menu(array(
            'title' => $label,
            'href' => $menu['url'],
            'parent' => 'rt_links',
            'id' => $menu['slug']
        ));
    }
}

/**
 * Creates rtPanel Options backup file
 * 
 * @uses $wpdb object
 *
 * @since rtPanel 2.0
 */
function rtp_export() {
    global $wpdb;
    $sitename = sanitize_key(get_bloginfo('name'));

    if (!empty($sitename))
        $sitename .= '.';

    $filename = $sitename . 'rtpanel.' . date('Y-m-d') . '.rtp';

    $general = "WHERE option_name = 'rtp_general'";
    $post_comments = "WHERE option_name = 'rtp_post_comments'";
    $hooks = "WHERE option_name = 'rtp_hooks'";
    $args['rtp_general'] = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} $general");
    $args['rtp_post_comments'] = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} $post_comments");
    $args['rtp_hooks'] = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} $hooks");

    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
    ?>
    <rtpanel>
        <rtp_version><?php echo maybe_serialize(rtp_export_version()); ?></rtp_version>
        <rtp_general><?php echo $args['rtp_general']; ?></rtp_general>
        <rtp_post_comments><?php echo $args['rtp_post_comments']; ?></rtp_post_comments>
    </rtpanel>
    <?php
}

/**
 * Restores rtPanel Options
 *
 * @uses $rtp_general array
 * @uses $rtp_post_comments array
 * @uses $rtp_hooks array
 * @param string $file The
 * @return bool|array
 *
 * @since rtPanel 2.0
 */
function rtp_import($file) {
    global $rtp_general, $rtp_post_comments;
    require_once( ABSPATH . '/wp-admin/includes/class-wp-filesystem-base.php' );
    require_once( ABSPATH . '/wp-admin/includes/class-wp-filesystem-direct.php' );
    require_once( ABSPATH . '/wp-admin/includes/file.php' );

    @$file_object = new WP_Filesystem_Direct;
    $overrides = array('test_form' => false, 'test_type' => false);
    $import_file = wp_handle_upload($file, $overrides);
    extract(wp_check_filetype($import_file['file'], array('rtp' => 'txt/rtp')));
    $data = wp_remote_get($import_file['url']);
    $file_object->delete($import_file['file']);
    if ($ext != 'rtp') {
        return 'ext';
    }
    if (is_wp_error($data)) {
        return false;
    } else {
        preg_match('/\<rtp_general\>(.*)<\/rtp_general\>/is', $data['body'], $general);
        preg_match('/\<rtp_post_comments\>(.*)<\/rtp_post_comments\>/is', $data['body'], $post_comments);
        if (!empty($post_comments[1])) {
            update_option('rtp_post_comments', maybe_unserialize($post_comments[1]));
        }
        return $general[1];
    }
}

/**
 * Adds Custom Logo to Admin Dashboard ;)
 *
 * @since rtPanel 2.0
 */
function rtp_custom_admin_logo() {
    echo '<style type="text/css"> #header-logo { background: url("' . RTP_IMG_FOLDER_URL . '/rtp-icon.jpg") no-repeat scroll center center transparent !important; max-width: 16px; height: auto; } </style>';
}

add_action('admin_head', 'rtp_custom_admin_logo');

/**
 * Adds custom footer text
 *
 * @since rtPanel 2.0
 */
function rtp_custom_admin_footer($footer_text) {
    echo $footer_text;
    echo '<br /><br />' . __('Currently using <a href="http://rtcamp.com/rtpanel/" title="rtPanel" target="_blank">rtPanel</a>', 'rtPanel') . ' | '
    . __('<a href="http://rtcamp.com/support/forum/rtpanel/" title="Click here for rtPanel Free Support" target="_blank">Support</a>', 'rtPanel') . ' | '
    . __('<a href="http://rtcamp.com/rtpanel/docs/" title="Click here for rtPanel Documentation" target="_blank">Documentation</a>', 'rtPanel');
}

add_filter('admin_footer_text', 'rtp_custom_admin_footer');

/**
 * Gets rtPanel and WordPress version
 *
 * @since rtPanel 2.0
 */
function rtp_export_version() {
    global $wp_version;
    require_once( ABSPATH . '/wp-admin/includes/update.php' );
    /* Backward Compatability for version prior to WordPress 3.4 */
    $theme_info = function_exists('wp_get_theme') ? wp_get_theme() : get_theme(get_current_theme());
    if (is_child_theme()) {
        $theme_info = function_exists('wp_get_theme') ? wp_get_theme('rtpanel') : get_theme($theme_info['Parent Theme']);
    }
    $theme_version = array('wp' => $wp_version, 'rtPanel' => $theme_info['Version']);
    return $theme_version;
}

/**
 * Gets rtPanel and WordPress version (in text) for footer
 *
 * @since rtPanel 2.0
 */
function rtp_version($update_footer) {
    global $rtp_version;
    $update_footer .= '<br /><br />' . __('rtPanel Version ', 'rtPanel') . $rtp_version['rtPanel'];
    return $update_footer;
}

add_filter('update_footer', 'rtp_version', 9999);

/**
 * Handles ajax call to remove the 'regenerate thumbnail' notice
 *
 * @uses $rtp_post_comments array
 *
 * @since rtPanel 2.0
 */
function rtp_handle_regenerate_notice() {
    global $rtp_post_comments;
    if (isset($_POST['hide_notice'])) {
        $rtp_post_comments['notices'] = '0';
        update_option('rtp_post_comments', $rtp_post_comments);
    }
}

add_action('wp_ajax_hide_regenerate_thumbnail_notice', 'rtp_handle_regenerate_notice');

/**
 *  Displays the regenerate thumbnail notice
 *
 * @since rtPanel 2.0
 */
function rtp_regenerate_thumbnail_notice($return = false) {
    if (current_user_can('administrator')) {
        if (is_plugin_active(RTP_REGENERATE_THUMBNAILS)) {
            $regenerate_link = admin_url('/tools.php?page=regenerate-thumbnails');
        } elseif (array_key_exists(RTP_REGENERATE_THUMBNAILS, get_plugins())) {
            $regenerate_link = admin_url('/plugins.php#regenerate-thumbnails');
        } else {
            $regenerate_link = wp_nonce_url(admin_url('update.php?action=install-plugin&plugin=regenerate-thumbnails'), 'install-plugin_regenerate-thumbnails');
        }

        if ($return) {
            return $regenerate_link;
        } else {
            echo '<div class="error regenerate_thumbnail_notice"><p>' . sprintf(__('The Thumbnail Settings have been updated. Please <a href="%s" title="Regenerate Thumbnails">Regenerate Thumbnails</a>.', 'rtPanel'), $regenerate_link) . ' <span class="alignright regenerate_thumbnail_notice_close" href="#">X</span></p></div>';
        }
    } else {
        return;
    }
}

/* Shows 'regenerate thumbnail' notice ( Admin User Only !!! ) */
if (is_admin() && @$rtp_post_comments['notices']) {
    add_action('admin_notices', 'rtp_regenerate_thumbnail_notice');
}

/**
 * Outputs neccessary script to hide 'regenerate thumbnail' notice
 *
 * @since rtPanel 2.0
 */
function rtp_regenerate_thumbnail_notice_js() {
    ?>
    <script type="text/javascript" >
        jQuery(function(){
            jQuery('#wpbody-content').css( 'padding-bottom', '85px' );
            jQuery('.regenerate_thumbnail_notice_close').css( 'color', '#CC0000' );
            jQuery('.regenerate_thumbnail_notice_close').css( 'cursor', 'pointer' );
            jQuery('.regenerate_thumbnail_notice_close').click(function(e){
                e.preventDefault();
                jQuery('.regenerate_thumbnail_notice').hide();
                // call ajax
                jQuery.ajax({
                    url:"<?php echo admin_url('admin-ajax.php'); ?>",
                    type:'POST',
                    data:'action=hide_regenerate_thumbnail_notice&hide_notice=1'
                });
            });
        });
    </script><?php
}

add_action('admin_head', 'rtp_regenerate_thumbnail_notice_js');

/* Removes 'regenerate thumbnail' notice ( Admin User Only !!! ) */
if (is_admin() && $pagenow == 'tools.php' && ( @$_GET['page'] == 'regenerate-thumbnails' ) && @$_POST['regenerate-thumbnails']) {
    $rtp_notice = get_option('rtp_post_comments');
    $rtp_notice['notices'] = '0';
    update_option('rtp_post_comments', $rtp_notice);
}

/* Check if regeneration of thumbnail is required, or not */
if (is_array($rtp_post_comments) && ( @$rtp_post_comments['thumbnail_width'] != get_option('thumbnail_size_w') || @$rtp_post_comments['thumbnail_height'] != get_option('thumbnail_size_h') || @$rtp_post_comments['thumbnail_crop'] != get_option('thumbnail_crop') )) {
    $rtp_post_comments['notices'] = '1';
    $rtp_post_comments['thumbnail_width'] = get_option('thumbnail_size_w');
    $rtp_post_comments['thumbnail_height'] = get_option('thumbnail_size_h');
    $rtp_post_comments['thumbnail_crop'] = get_option('thumbnail_crop');
    update_option('rtp_post_comments', $rtp_post_comments);
}

/**
 * Adds Styles dropdown to TinyMCE Editor
 *
 * @since rtPanel 2.1
 */
function rtp_mce_editor_buttons($buttons) {
    array_unshift($buttons, 'styleselect');
    return $buttons;
}

add_filter('mce_buttons_2', 'rtp_mce_editor_buttons');

/**
 * Adds Non Semantic Helper classes/styles dropdown to TinyMCE Editor
 *
 * @since rtPanel 2.1
 */
function rtp_mce_before_init($settings) {

    $style_formats = array(
        array(
            'title' => 'Clean',
            'block' => 'p',
            'classes' => 'clean',
            'wrapper' => false
        ),
        array(
            'title' => 'Alert',
            'block' => 'p',
            'classes' => 'alert',
            'wrapper' => false
        ),
        array(
            'title' => 'Info',
            'block' => 'p',
            'classes' => 'info',
            'wrapper' => false
        ),
        array(
            'title' => 'Success',
            'block' => 'p',
            'classes' => 'success',
            'wrapper' => false
        ),
        array(
            'title' => 'Warning',
            'block' => 'p',
            'classes' => 'warning',
            'wrapper' => false
        ),
        array(
            'title' => 'Error',
            'block' => 'p',
            'classes' => 'error',
            'wrapper' => false
        )
    );

    $settings['style_formats'] = json_encode($style_formats);
    return $settings;
}

add_filter('tiny_mce_before_init', 'rtp_mce_before_init');

/**
 * Adds favicon image to the list of generated images ( For Logo/Favicon Settings )
 *
 * @since rtPanel 2.2
 */
function rtp_create_favicon($sizes) {
    $sizes['favicon'] = array('width' => 16, 'height' => 16, 'crop' => 1);
    return $sizes;
}

/**
 * Displays a message for child theme users regarding change in header.php
 *
 * @since rtPanel 3.2
 */
function rtpanel_upgrade_32_notice() {
    if (is_child_theme() && !get_site_option('rtp_upgrade_32')) {
        ?>
        <div class="updated rtp_upgrade_32_notice">
            <p><?php _e('<strong>Note:</strong> If you have overriden the header.php in your child theme make sure you place <strong>rtp_head();</strong> on the line just after <strong>wp_head();</strong>. This hook is being used to output your custom css.', 'rtPanel'); ?><a class="alignright rtp_upgrade_32" href="#">X</a></p>
        </div><?php
    }
}

add_action('admin_notices', 'rtpanel_upgrade_32_notice');

/**
 * Handles ajax call to remove the rtp_upgrade_32_notice
 *
 * @since rtPanel 3.2
 */
function rtp_remove_upgrade_32_notice() {
    if (is_child_theme()) {
        update_site_option('rtp_upgrade_32', true);
    }
    die();
}

add_action('wp_ajax_remove_upgrade_32_notice', 'rtp_remove_upgrade_32_notice');

/**
 * Outputs neccessary script to remove rtp_upgrade_32_notice
 *
 * @since rtPanel 3.2
 */
function rtp_upgrade_32_notice_js() {
    if (is_child_theme() && !get_site_option('rtp_upgrade_32')) {
        ?>
        <script type="text/javascript" >
            jQuery(function(){
                jQuery('.rtp_upgrade_32').css( 'color', '#CC0000' );
                jQuery('.rtp_upgrade_32').click(function(e){
                    e.preventDefault();
                    jQuery('.rtp_upgrade_32_notice').hide();
                    // call ajax
                    jQuery.ajax({
                        url:"<?php echo admin_url('admin-ajax.php'); ?>",
                        type:'POST',
                        data:'action=remove_upgrade_32_notice'
                    });
                });
            });
        </script><?php
    }
}

add_action('admin_head', 'rtp_upgrade_32_notice_js');