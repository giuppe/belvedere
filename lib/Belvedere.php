<?php
require_once "vendors/simple_html_dom/simple_html_dom.php";
require_once 'vendors/markdown/markdown.php';
require_once 'vendors/Cake/Utility/Sanitize.php';

mb_internal_encoding("UTF-8");

class BlvSyntaxException extends Exception{};
class ParentSelectNotExistsException extends BlvSyntaxException{};

class Belvedere
{

    private $output_dir = "output";

    public $base_dir;

    public $site_config = array(
        'title' => "My Site",
        'robots' => 'index, follow',
        'description' => 'A Belvedere Default Site',
        'date_format' => 'jS M Y',
        'time_format' => 'H:m:s',
        'theme' => 'rindu'
    );

    public $theme_config = array(
        "pages" => array(
            "index" => array(
                'skin' => 'index.html'
            ),
            "blog" => array(
                'skin' => 'blog.html'
            )
        )
    );

    private $logs = array();

    public function blv_log($message)
    {
        $this->logs[] = strval($message);
    }

    public function flush_log()
    {
        $results = "";
        $results .= "<ul>";
        foreach ($this->logs as $message) {
            $results .= "<li>" . $message . "</li>";
        }
        $results .= "</ul>";
        echo $results;
    }

    public function __construct($base_dir = ".")
    {
        $this->base_dir = $base_dir;
    }

    public function getOutputDir()
    {
        return $this->base_dir . DIRECTORY_SEPARATOR . $this->output_dir;
    }

    protected function strip_p($t)
    {
        return preg_replace('{</?p>}i', '', $t);
    }

    protected function init_config()
    {
        $site_config = json_decode(file_get_contents("content/site_config.json"), true);
        $this->site_config = array_replace_recursive($this->site_config, $site_config);
        $theme_config = json_decode(file_get_contents("themes/" . $this->site_config['theme'] . ".json"), true);
        $this->theme_config = array_replace_recursive($this->theme_config, $theme_config);
    }

    protected function getRecursiveDirs($dir)
    {
        $result = array();
        
        $cdir = scandir($dir);
        foreach ($cdir as $key => $value) {
            if (! in_array($value, array(
                ".",
                ".."
            ))) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                    $result[$value] = $this->getRecursiveDirs($dir . DIRECTORY_SEPARATOR . $value);
                } else {
                    $result[] = $value;
                }
            }
        }
        
        return $result;
    }

    protected function copy_recursive_dirs($source, $dest)
    {
        foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            if ($item->isDir()) {
                mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                $this->blv_log("Created dir: " . $item);
            } else {
                if ($item->getExtension() != "html" && $item->getExtension() != "htm") {
                    
                    copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                }
            }
        }
    }

    protected function copy_theme_dirs()
    {
        $this->blv_log("Start theme copying");
        $this->copy_recursive_dirs($this->base_dir . DIRECTORY_SEPARATOR . "themes" . DIRECTORY_SEPARATOR . $this->site_config['theme'], $this->getOutputDir());
    }

    protected function copy_images_dirs()
    {
        $this->blv_log("Start images copying");
        $this->copy_recursive_dirs($this->base_dir . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR, $this->getOutputDir());
    }

    /**
     * Scandir without dot directories
     *
     * @param string $path
     *            The directory that will be scanned
     * @return array an array of filenames on success, or false on failure
     */
    protected function blv_scandir($path)
    {
        return array_diff(scandir($path), array(
            '..',
            '.'
        ));
    }

    public function getThemeConfigFilenames()
    {}

    public function getContentFilenames()
    {
        return $this->getRecursiveDirs($this->base_dir . DIRECTORY_SEPARATOR . "content");
    }

    public function getContentFileContent($filename, $dir)
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $filename);
        $sanitized_dir = preg_replace('/[^a-zA-Z0-9-_]/', '', $dir);
        $path = $this->base_dir . DIRECTORY_SEPARATOR . "content" . DIRECTORY_SEPARATOR . $sanitized;
        if (! empty($sanitized_dir)) {
            $path = "content" . DIRECTORY_SEPARATOR . $sanitized_dir . DIRECTORY_SEPARATOR . $sanitized;
        }
        $file_content = file_get_contents($path);
        if ($file_content === false) {
            return array(
                "error" => "Unable to load file"
            );
        }
        $metadata = $this->read_properties($file_content);
        $text = trim($this->strip_properties($file_content));
        return array(
            'metadata' => $metadata,
            'text' => $text,
            'name' => $sanitized,
            'dir' => $sanitized_dir
        );
    }

    public function saveContentFileContent($filename, $dir, $content)
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $filename);
        $sanitized_dir = preg_replace('/[^a-zA-Z0-9-_]/', '', $dir);
        $path = $this->base_dir . DIRECTORY_SEPARATOR . "content" . DIRECTORY_SEPARATOR . $sanitized;
        if (! empty($sanitized_dir)) {
            $path = "content" . DIRECTORY_SEPARATOR . $sanitized_dir . DIRECTORY_SEPARATOR . $sanitized;
        }
        file_put_contents($path, $content);
        
        return array(
            'save_date' => date($this->site_config['date_format'] . " " . $this->site_config['time_format']),
            'name' => $sanitized,
            'dir' => $sanitized_dir
        );
    }

    /**
     * Remove everything from the output_dir and reinstalls the default htaccess
     *
     * @param string $output_dir
     *            path of the output directory
     */
    public function clean_output_dir($output_dir)
    {
        $source = $output_dir;
        
        foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $this->blv_log("Removing: " . $iterator->getSubPathName());
            if ($item->isDir()) {
                rmdir($source . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                
                unlink($source . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
        
        $htaccess = "<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.html [L]
</IfModule>";
        file_put_contents($output_dir . DIRECTORY_SEPARATOR . ".htaccess", $htaccess);
    }

    /**
     * Reads the metadata in the format @varname varvalue
     *
     * @param string $content            
     * @return array metadata variable names as keys and values as values
     */
    public function read_properties($content)
    {
        $results = array();
        $matches = array();
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
            preg_match('#^@(\\w+)\\s(.*)$#s', $line, $matches);
            if (! empty($matches)) {
                $this->blv_log(print_r($matches, true));
                $results["@" . $matches[1]] = $matches[2];
            }
        }
        
        return $results;
    }

    public function read_content($content)
    {
        return Markdown($this->strip_properties($content));
    }

    public function strip_properties($content)
    {
        $result = "";
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
            $result .= preg_replace('#^@.*$#s', '', $line) . "\n";
        }
        return $result;
    }

    var $selection_contexts = array();

    /**
     * @var boolean
     */
    var $temp_dom_append;
    /**
     * @var
     */
    var $current_theme;

    public function _create($tagname){
        $this->selection_contexts[] = array(
            "tag"=> sprintf("<%s>", $tagname),
            "target_selector" =>"",
            "op" => "create"
        );
    }

    public function _append($selector){
        $current_sel = array_pop($this->selection_contexts);
        $current_sel['target']=$selector;
        $current_sel['insert_type']="append";
        array_push($this->selection_contexts, $current_sel);

    }

    public function _setTheme($filename){
        $this->current_theme = file_get_html($filename);
    }

    public function _setText($text){
        $current_sel = array_pop($this->selection_contexts);
        $current_sel['content_ops']["set_text"] = $text;
        array_push($this->selection_contexts, $current_sel);
    }

    public function _setAttr($attr, $content){
        if(empty($this->selection_contexts)){
            $this->blv_log("Error: setAttr: no selection context");
        }
        else {
        $current_sel = array_pop($this->selection_contexts);
        //$this->blv_log(sprintf("setAttr %s, %s on %s", $attr,  $content, print_r($this->temp_dom, true)));

            $current_sel['content_ops']['set_attributes'][] = array($attr=>$content);
            array_push($this->selection_contexts, $current_sel);
        }

    }



    public function _subSelect($selector){
        $parent_sel = null;
        foreach($this->selection_contexts as $op){
            if(array_key_exists('subselect', $op)&&$op['subselect']===false){
                $parent_sel = $op;
            }
        }
        if(empty($parent_sel)){
            throw new ParentSelectNotExistsException();
        }

        $new_op = array(
            "op" => "select",
            "selector" => $parent_sel['selector']." ".$selector,
            "subselect" => true,
        );

        if(array_key_exists('content_properties', $op)){
            $new_op['content_properties'] = $op['content_properties'];
        }

        if(array_key_exists('content_text', $op)){
            $new_op['content_text'] = $op['content_text'];
        }

        if(array_key_exists('load', $op)){
            $new_op['load'] = $op['load'];
        }

        $this->selection_contexts[] = $new_op;
    }

    public function _remove($selector){
        $this->selection_contexts[] = array(
            "op" => "remove",
            "selector" => $selector,

    );
    }

    public function _select($selector){
        $this->selection_contexts[] = array(
            "op" => "select",
            "selector" => $selector,
            "subselect" => false
        );

    }

    private function handle_create($theme_html, $tag, $target, $insert_type="append", $content_ops=array()){
        $theme = str_get_html($theme_html);
        $set_text = (array_key_exists('set_text', $content_ops))? $content_ops['set_text'] : "";

        $set_attributes = (array_key_exists('set_attributes', $content_ops))? $content_ops['set_attributes'] : array();
        $targets = $theme->find($target);
        foreach($targets as $t){
            $new_tag = str_get_html($tag);
            if(!empty($set_text)){
                $new_tag->find($tag, 0)->innertext = $set_text;
            }
            if(!empty($set_attributes)){
                foreach($set_attributes as $attribute){
                    list($attr_name, $attr_value) = each($attribute);

                    $new_tag->firstChild()->$attr_name = $attr_value;
                }
            }
            if($insert_type=="append") {
                $text = strval($new_tag);
                $t->innertext = $t->innertext.$text;
            }
            elseif($insert_type=="insert"){
                $t->innertext = $text.$t->innertext;
            }
        }
        return strval($theme);
    }

    private function handle_remove($theme_html, $target){
        $theme = str_get_html($theme_html);
        $targets = $theme->find($target);
        foreach($targets as $t){
            $t->outertext="";
        }
        return strval($theme);
    }

    private function handle_select($theme_html, $selector, $content_ops=array()){
        $theme = str_get_html($theme_html);
        $set_text = (array_key_exists('set_text', $content_ops))? $content_ops['set_text'] : "";

        $set_attributes = (array_key_exists('set_attributes', $content_ops))? $content_ops['set_attributes'] : array();


        $targets = $theme->find($selector);
        foreach($targets as $t){
            if(!empty($set_text)){
                $t->innertext = $set_text;
            }
            if(!empty($set_attributes)){
                foreach($set_attributes as $attribute){
                    list($attr, $attr_value) = each($attribute);
                    $t->$attr = $attr_value;
                }
            }
        }
        return strval($theme);
    }

    private function handle_clone($theme_html, $selector, $content_properties=array(), $times=0,$content_ops=array()){
        $theme = str_get_html($theme_html);
        //TODO finire funzione clone
        return strval($theme);
    }


    public function _load($filename){
        $this->blv_log("Loading file: ".$filename);
        $content = file_get_contents($filename);
        $this->blv_log("Loaded content: " . htmlentities($content));

        $current_sel = array_pop($this->selection_contexts);
        $current_sel['load']="file";
        $current_sel['content_properties'] = $this->read_properties($content);
        $current_sel['content_text'] = $this->read_content($content);
        $current_sel['content_properties']['@content'] = $content;
        array_push($this->selection_contexts, $current_sel);
    }

    private function _preprocess_properties($op){

        if(!empty($op['content_ops']['set_attributes'])){
            foreach($op['content_ops']['set_attributes'] as $attr_key=>$attribute){
                list($attr_name, $attr_value) = each($attribute);
                if(mb_strpos($attr_value,"@")===0){
                    $new_attr_value = $op['content_properties'][$attr_value];
                    $op['content_ops']['set_attributes'][$attr_key] = array($attr_name=> $new_attr_value);
                }
            }
        }
        if(!empty($op['set_text'])){
            if(mb_strpos($op['content_ops']['set_text'],"@")===0){
                $op['content_ops']['set_text'] = $op['content_properties'][$op['content_ops']['set_text']];
            }
        }
        return $op;
    }

    public function _write(){
        foreach($this->selection_contexts as $key => $op){
            if(!empty($op['load'])){
                $this->selection_contexts[$key] = $this->_preprocess_properties($op);

            }
        }
        foreach($this->selection_contexts as $op){
            $current_theme = strval($this->current_theme);

            switch($op['op']) {
                case "select":
                    $modified_theme= $this->handle_select($current_theme, $op['selector'],$op['content_ops']);
                    break;
                case "remove":
                    $modified_theme = $this->handle_remove($current_theme, $op['selector']);
                    break;
                case "create":
                    $modified_theme = $this->handle_create($current_theme, $op['tag'], $op['target'], $op['insert_type'], $op['content_ops']);
                    break;
                case "clone":
                    $modified_theme = $this->handle_clone($current_theme, $op['selector'], $op['file_list'], $op['times'],$op['content_ops']);
            }
            $this->current_theme = str_get_html($modified_theme);

        }

        $this->selection_contexts = array();
    }


    public function execute()
    {
        $this->init_config();
        $this->clean_output_dir($this->getOutputDir());
        $this->copy_theme_dirs();
        $this->copy_images_dirs();
        
        foreach ($this->theme_config['pages'] as $page_id => $page) {
            if (! array_key_exists("skin", $page)) {
                $page['skin'] = "index.html";
            }
            
            ob_start();

            $this->_setTheme($this->base_dir . DIRECTORY_SEPARATOR . "themes" . DIRECTORY_SEPARATOR . $this->site_config['theme'] . "/" . $page['skin']);
            //$theme = file_get_html($this->base_dir . DIRECTORY_SEPARATOR . "themes" . DIRECTORY_SEPARATOR . $this->site_config['theme'] . "/" . $page['skin']);
            $this->_select("meta[name=description]");
            $this->_setAttr("content", $this->site_config['description']);
            $this->_write();
            //$theme->find("meta[name=description]", 0)->content = $this->site_config['description'];
            $meta_robots = $this->current_theme->find("meta[name=robots]", 0);
            if (empty($meta_robots)) {
                $this->_create("meta");
                $this->_setAttr("name", "robots");
                $this->_setAttr("content", $this->site_config['robots']);
                $this->_append("head");
                $this->_write();
                //$head = $theme->find('head', 0);
                //$head->innertext = "<meta name='robots' content='" . $this->site_config['robots'] . "' />\n" . $head->innertext;
            } else {
                $meta_robots->content = $this->site_config['robots'];
            }
            $this->_select("title");
            $this->_setText($this->site_config['title']);
            $this->_write();
            //$this->current_theme->find("title", 0)->innertext = $this->site_config['title'];

            $this->_select("body");
            $this->_load("content" . DIRECTORY_SEPARATOR ."about".DIRECTORY_SEPARATOR."about_subtitle.txt");
            $this->_setAttr("pippo", "@subtitle");
            $this->_subSelect("nav div");
            $this->_setAttr("boh", "chenneso");
            $this->_write();

            if (array_key_exists("fragments", $page)) {
                foreach ($page['fragments'] as $fragment) {
                    $this->blv_log("Fragment: " . print_r($fragment, true));
                    $selected = $this->current_theme->find($fragment['selector']);
                    foreach ($selected as $s) {
                        if (array_key_exists('innertext', $fragment)) {
                            $s->innertext = $fragment['text'];
                        }
                        if (array_key_exists('clone', $fragment)) {
                            $subtheme_string = strval($this->current_theme->find($fragment['selector'], 0));
                            $this->blv_log("Subtheme: " . htmlentities($subtheme_string));
                            $content_dir = "content" . DIRECTORY_SEPARATOR . $fragment['clone']['content-dir'];
                            
                            $cloned_tags = "";
                            foreach ($this->blv_scandir($content_dir) as $file) {
                                $file_name = $content_dir . DIRECTORY_SEPARATOR . $file;
                                $this->blv_log("filename: " . $file_name);
                                $content = file_get_contents($file_name);
                                $this->blv_log("content: " . htmlentities($content));
                                $metadata = $this->read_properties($content);
                                $content = $this->read_content($content);
                                $metadata["@content"] = $content;
                                $this->blv_log("metadata: " . htmlentities(print_r($metadata, true)));
                                $subtheme = str_get_html($subtheme_string);
                                foreach ($fragment['clone']['fragments'] as $subfragment) {
                                    
                                    $tag = $subfragment['content'];
                                    $this->blv_log("tag: " . $tag);
                                    
                                    if (! array_key_exists('attr', $subfragment)) {
                                        $metadata_tag = "";
                                        if(array_key_exists($tag, $metadata)){
                                            $metadata_tag = $metadata[$tag];
                                        }
                                        $subtheme->find($subfragment['selector'], 0)->innertext = $metadata_tag;
                                    } else {
                                        $old_classes = "";
                                        if ($subfragment['attr'] == 'addClass') {
                                            $subfragment['attr'] = 'class';
                                            $old_classes = $subtheme->find($subfragment['selector'], 0)->{$subfragment['attr']};
                                        }
                                        $subtheme->find($subfragment['selector'], 0)->{$subfragment['attr']} = $old_classes . " " . $metadata[$tag];
                                    }
                                    if (array_key_exists('removeClass', $subfragment)) {
                                        $classes = explode(' ', $subtheme->find($subfragment['selector'], 0)->class);
                                        $classes_to_remove = explode(' ', $subfragment['removeClass']);
                                        $subtheme->find($subfragment['selector'], 0)->class = implode(' ', array_diff($classes, $classes_to_remove));
                                    }
                                }
                                $cloned_tags .= strval($subtheme);
                                $this->blv_log("cloned_tags current: " . htmlentities($cloned_tags));
                            }
                            $this->current_theme->find($fragment['selector'], 0)->parent()->innertext = $cloned_tags;
                            break;
                        } elseif (array_key_exists('content', $fragment)) {
                            if (is_array($fragment['content'])) {
                                
                                if (array_key_exists('file', $fragment['content']) && array_key_exists('fragments', $fragment['content'])) {
                                    foreach ($fragment['content']['fragments'] as $subfragment) {
                                        $file_name = "content" . DIRECTORY_SEPARATOR . $fragment['file'];
                                        $this->blv_log("filename: " . $file_name);
                                        $content = file_get_contents($file_name);
                                        $this->blv_log("content: " . htmlentities($content));
                                        $metadata = $this->read_properties($content);
                                        $content = $this->read_content($content);
                                        $metadata["@content"] = $content;
                                        $this->blv_log("metadata: " . htmlentities(print_r($metadata, true)));
                                        $subtheme = str_get_html($s->innertext);
                                        foreach ($fragment['content']['fragments'] as $subfragment) {
                                            
                                            $tag = $subfragment['content'];
                                            $this->blv_log("tag: " . $tag);
                                            
                                            if (! array_key_exists('attr', $subfragment)) {
                                                $subtheme->find($subfragment['selector'], 0)->innertext = $metadata[$tag];
                                            } else {
                                                $old_classes = "";
                                                if ($subfragment['attr'] == 'addClass') {
                                                    $subfragment['attr'] = 'class';
                                                    $old_classes = $subtheme->find($subfragment['selector'], 0)->{$subfragment['attr']};
                                                }
                                                $subtheme->find($subfragment['selector'], 0)->{$subfragment['attr']} = $old_classes . " " . $metadata[$tag];
                                            }
                                            if (array_key_exists('removeClass', $subfragment)) {
                                                $classes = explode(' ', $subtheme->find($subfragment['selector'], 0)->class);
                                                $classes_to_remove = explode(' ', $subfragment['removeClass']);
                                                $subtheme->find($subfragment['selector'], 0)->class = implode(' ', array_diff($classes, $classes_to_remove));
                                            }
                                        }
                                        $cloned_tags .= strval($subtheme);
                                        $this->blv_log("cloned_tags current: " . htmlentities($cloned_tags));
                                    }
                                    $s->innertext = $cloned_tags;
                                }
                            } else {
                                $content = Markdown(file_get_contents("content" . DIRECTORY_SEPARATOR . $fragment['content']));
                                if (array_key_exists('strip_p', $fragment) && $fragment['strip_p'] === true) {
                                    $content = $this->strip_p($content);
                                }
                                
                                $html_content = str_get_html($content);
                                
                                if (array_key_exists('transform', $fragment)) {
                                    foreach ($fragment['transform'] as $selector => $operations) {
                                        foreach ($html_content->find($selector) as $e) {
                                            if (array_key_exists('class', $operations))
                                                $e->class = $operations['class'];
                                            if (array_key_exists('addClass', $operations))
                                                $e->class = $operations['addClass'] . " " . $e->class;
                                            if (array_key_exists('removeClass', $operations)) {
                                                $classes = explode(" ", $e->class);
                                                $classes_to_remove = explode(" ", $operations['removeClass']);
                                                $e->class = implode(" ", array_diff($classes, $classes_to_remove));
                                            }
                                        }
                                    }
                                }
                                $s->innertext = strval($html_content);
                            }
                        }
                        
                        if (array_key_exists('remove', $fragment) && $fragment['remove'] === true) {
                            $this->blv_log("Removing fragment: " . $fragment['selector']);
                            $this->_remove($fragment['selector']);
                            $this->_write();
                        }
                    }
                }
            }
            
            $html_fragment = $this->current_theme->save();
            
            echo $html_fragment;
            $html_to_write = ob_get_clean();
            file_put_contents($this->getOutputDir() . "/" . $page_id . ".html", $html_to_write);
        }
    }
}