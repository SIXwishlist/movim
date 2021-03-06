<?php

class NewsNav extends \Movim\Widget\Base
{
    public function load()
    {
    }

    public function display()
    {
        $nd = new \Modl\PostnDAO;

        $blogs = $nd->getLastBlogPublic(rand(0, 5), 5);
        $blogs = is_array($blogs) ? $blogs : [];

        shuffle($blogs);

        $this->view->assign('blogs', $blogs);

        $origin = ($this->get('s') && $this->get('s') != 'subscriptions') ?
            $this->get('s') : false;

        $posts = $nd->getLastPublished($origin, 0, 6);
        $posts = is_array($posts) ? $posts : [];

        shuffle($posts);

        $this->view->assign('posts', $posts);
    }
}
