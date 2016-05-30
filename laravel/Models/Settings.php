<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Settings extends Model
{
    protected $table = 'settings';
    
    public static $settings = array();

    public static function is_template ($name, $plugin, $sidebar = 0){
        $template = Settings::where('name', 'default_site_template')->pluck('value');
        if(!$template) return false;
        if($plugin){ // if we got plugin
            $path = base_path() . '/app/Themes/'.$template . '/plugins';
            if(!file_exists($path))
                return false;
            $files = scandir($path);
            if($sidebar)
                $name_full = strtolower($name) . 'sidebar.blade.php';
            else
                $name_full = strtolower($name) . '.blade.php';

            if(in_array($name_full, $files)){
                return $path . '/' . $name_full;
            }else{
                return false;
            }
        }else{ // if we got widget
            $path = base_path() . '/app/Themes/'.$template . '/widgets';
            if(!file_exists($path))
                return false;
            $files = scandir($path);
            $name_full = strtolower($name) . '.blade.php';

            if(in_array($name_full, $files)){
                return $path . '/' . $name_full;
            }else{
                return false;
            }
        }
    }

    public static function checkTemplate($name){
        $template = Settings::where('name', 'default_site_template')->pluck('value');
        if(!$template) return false;

        $path = base_path() . '/app/Themes/'.$template;
        if(!file_exists($path))
            return false;
        $files = scandir($path);
        $name_full = strtolower($name) . '.blade.php';
        if(in_array($name_full, $files)){
            return $template . '.' . $name;
        }else{
            return false;
        }
    }
    
    public static function setSetting($key, $value)
    {
        if(!$obj = self::where('name', $key)->first())
        {
            $obj = new self;
            $obj->name = $key;
        }
        $obj->value = $value;
        $obj->save();
    }
    
    public static function getSetting($name, $default = false)
    {
        if(!isset(self::$settings[$name]))
        {
            $val = self::where('name', $name)->first();
            if($val)
            {
                self::$settings[$name] = $val->value;
            }
            else
            {
                if($default)
                    self::$settings[$name] = $default;
                else
                    self::$settings[$name] = false;
            }
        }
        return self::$settings[$name];
    }
}
