<?php
/*
 * @file WidgetWraper.php
 *
 * @brief Handle the Widgets
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

class WidgetWrapper
{
    private static $instance;

    private $_widgets = array();
    private $_events = array();

    private $_view = ''; // The current page where the widget is displayed

    private $css = array(); // All the css loaded by the widgets so far.
    private $js = array(); // All the js loaded by the widgets so far.

    private function __construct()
    {
    }

    public function registerAll($load = false)
    {
        $widgets_dir = scandir(APP_PATH ."widgets/");

        foreach($widgets_dir as $widget_dir) {
            if(is_dir(APP_PATH ."widgets/".$widget_dir) &&
                $widget_dir != '..' &&
                $widget_dir != '.') {
                if($load) $this->loadWidget($widget_dir, true);
                array_push($this->_widgets, $widget_dir);
            }
        }
    }

    static function getInstance()
    {
        if(!is_object(self::$instance)) {
            self::$instance = new WidgetWrapper;
        }
        return self::$instance;
    }

    static function destroyInstance()
    {
        if(isset(self::$instance)) {
            self::$instance = null;
        }
    }

    /**
     * @desc Set the view
     * @param $page the name of the current view
     */
    public function setView($view)
    {
        $this->_view = $view;
    }

    /**
     * @desc Loads a widget and returns it
     * @param $name the name of the widget
     * @param $register know if we are loading in the daemon or displaying
     */
    public function loadWidget($name, $register = false)
    {
        if(file_exists(APP_PATH . "widgets/$name/$name.php")) {
            $path = APP_PATH . "widgets/$name/$name.php";
        }
        else {
            throw new Exception(
                __('error.widget_load_error', $name));
        }

        require_once($path);

        if($register) {
            $widget = new $name(true);
            // We save the registered events of the widget for the filter
            if(isset($widget->events)) {
                foreach($widget->events as $key => $value) {
                    if(is_array($this->_events)
                    && array_key_exists($key, $this->_events)) {
                        $we = $this->_events[$key];
                        array_push($we, $name);
                        $we = array_unique($we);
                        $this->_events[$key] = $we;
                    } else {
                        $this->_events[$key] = array($name);
                    }
                }
            }
            unset($widget);
        } else {
            if($this->_view != '') {
                $widget = new $name(false, $this->_view);
            } else {
                $widget = new $name();
            }
            // Collecting stuff generated by the widgets.
            $this->css = array_merge($this->css, $widget->loadcss());
            $this->js = array_merge($this->js, $widget->loadjs());

            return $widget;
        }
    }

    /**
     * @desc Loads a widget and runs a particular function on it.
     *
     * @param $widget_name is the name of the widget.
     * @param $method is the function to be run.
     * @param $params is an array containing the parameters to
     *   be passed along to the method.
     * @return what the widget's method returns.
     */
    function runWidget($widget_name, $method, array $params = null)
    {
        $widget = $this->loadWidget($widget_name);

        if(!is_array($params))
            $params = array();

        $result = call_user_func_array(array($widget, $method), $params);

        unset($widget, $method, $params);

        return $result;
    }

    /**
     * Calls a particular function with the given parameters on
     * all loaded widgets.
     *
     * @param $key is the key of the incoming event
     * @param $data is the Packet that is sent as a parameter
     */
    function iterate($key, $data)
    {
        if(array_key_exists($key, $this->_events)) {
            foreach($this->_events[$key] as $widget_name) {
                $widget = new $widget_name(true);
                if(array_key_exists($key, $widget->events)) {
                    foreach($widget->events[$key] as $method) {
                        /*
                         * We check if the method need to be called if the
                         * session notifs_key is set to a specific value
                         */
                        if(is_array($widget->filters)
                        && array_key_exists($method, $widget->filters)) {
                            $session = Session::start();
                            $notifs_key = $session->get('notifs_key');

                            if($notifs_key == 'blurred') {
                                $widget->{$method}($data);
                            } else {
                                $explode = explode('|', $notifs_key);
                                $notif_key = reset($explode);
                                if($notif_key == $widget->filters[$method]) {
                                    $widget->{$method}($data);
                                }
                            }
                            unset($session, $notifs_key);
                        } else {
                            $widget->{$method}($data);
                        }
                    }
                }
                unset($widget);
            }
        }

        unset($key, $data);
    }

    /**
     * @desc Returns the list of loaded CSS.
     */
    function loadcss()
    {
        return $this->css;
    }

    /**
     * @desc Returns the list of loaded javascripts.
     */
    function loadjs()
    {
        return $this->js;
    }
}
