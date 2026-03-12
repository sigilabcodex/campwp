<?php

namespace CampWP\Storage;

class LocalStorage
{
    public function register(): void
    {
        add_filter('upload_dir', [$this, 'register_release_subdir']);
    }

    public function register_release_subdir(array $uploads): array
    {
        if (! empty($_REQUEST['post_id'])) {
            $post_id = absint($_REQUEST['post_id']);
            $post_type = get_post_type($post_id);
            if (in_array($post_type, ['campwp_album', 'campwp_track'], true)) {
                $uploads['subdir'] = '/campwp' . $uploads['subdir'];
                $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
                $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
            }
        }

        return $uploads;
    }
}
