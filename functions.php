<?php

class IrbisExport {

    private $record;
    private $srcFolder;
    private $required_fields = array(
      '700' => array('A', 'B'),
      '200' => array('A', 'F'),
      '463' => array('C', 'J', 'G', 'S')
    );

    function __construct($record, $srcFolder) {
        $this->record = $this->init($record);
        $this->srcFolder = $srcFolder;
    }


    function parse_arr($field) {
        $item_arr = array();
        foreach ($field as $itemkey => $item) {
            list($itempart1, $itempart2) = explode('.', $itemkey);

            if ($itempart1 == 'SUBFIELD') {
                $item_arr[$itempart2] = $item;
            } elseif (is_array($item)) {
                $item_arr[] = $this->parse_arr($item);
            } else {
                $item_arr[] = $item;
            }
        }
        return $item_arr;
    }

    function init($record) {
        $fields = array();
        foreach ($record as $key => $field) {
            $parts = explode('.', $key);
            if ($parts[0] == 'FIELD' && is_array($field)) {
                $item_arr = array();
                foreach ($field as $itemkey => $item) {
                    $subparts = explode('.', $itemkey);
                    if (!empty($subparts[0]) && $subparts[0] == 'SUBFIELD') {
                        $item_arr[$subparts[1]] = $item;
                    } elseif (is_array($item)) {
                        $fields[$parts[1] . '_' . $itemkey] = $this->parse_arr($item);
                    }
                }
                $fields[$parts[1]] = $item_arr;
            } else {
                $fields[$parts[0]] = $field;
            }
        }
        return $fields;
    }

    function getRecord() {
        return $this->record;
    }

    function getRecordName() {
        if ($this->validate()) {
            return $this->record['200']['A'];
        }
        return false;
    }


    function xmlInit() {
        if (isset($this->record['700']['A'])) {
            $this->xmlData[] = array(
              'attr' => array('element' => 'contributor',
                'qualifier' => 'author'),
              'value' => $this->record['700']['A'] . ', ' . $this->record['700']['B']
            );
        }
        if (isset($this->record['701']['A'])) {
            $this->xmlData[] = array(
              'attr' => array('element' => 'contributor',
                'qualifier' => 'author'),
              'value' => $this->record['701']['A'] . ', ' . $this->record['701']['B']
            );
        }
        $i = 0;
        while (isset($this->record['701_' . $i])) {
            $this->xmlData[] = array(
              'attr' => array(
                'element' => 'contributor',
                'qualifier' => 'author',
                'field' => '701_' . $i
              ),
              'value' => $this->record['701_' . $i]['A'] . ', ' . $this->record['701_' . $i]['B']
            );
            $i++;
        }
        if (isset($this->record['463']['J'])) {
            $this->xmlData[] = array(
              'attr' => array('element' => 'date',
                'qualifier' => 'issued'),
              'value' => $this->record['463']['J']
            );
        }
        $this->xmlData[] = array(
          'attr' => array('element' => 'identifier',
            'qualifier' => 'citation',
            'language' => "ru_RU"),
          'value' => $this->getBibOpys(),
        );
        $this->xmlData[] = array(
          'attr' => array('element' => 'language',
            'qualifier' => 'iso',
            'language' => "ru_RU"),
          'value' => 'uk'
        );
        if (isset($this->record['963']['J'])) {
            $this->xmlData[] = array(
              'attr' => array('element' => 'publisher',
                'language' => "ru_RU"),
              'value' => $this->record['963']['J']
            );
        }
        if (isset($this->record['200']['A'])) {
            $this->xmlData[] = array(
              'attr' => array(
                'element' => 'title',
                'language' => "ru_RU"),
              'value' => $this->record['200']['A'] . ' ' . ((isset($this->record['200']['E'])) ? (': ' . $this->record['200']['E']) : ''));
        }
        if (isset($this->record['463']['G'])) {
            $this->xmlData[] = array(
              'attr' => array(
                'element' => 'publisher',
                'language' => "ru_RU"),
              'value' => $this->record['463']['G']
            );
        }
        $this->xmlData[] = array(
          'attr' => array(
            'element' => 'type',
            'language' => "ru_RU"),
          'value' => 'Article'
        );
    }

    function xmlGenerate() {
        $this->xmlInit();
        $string = '<?xml version="1.0" encoding="utf-8" standalone="no"?>
    <dublin_core schema="dc">';
        foreach ($this->xmlData as $val) {
            $string .= '  <dcvalue ';
            foreach ($val['attr'] as $attrName => $attr) {
                $string .= ' ' . $attrName . '="' . $attr . '"';
            }
            $string .= '>' . $val['value'] . '</dcvalue>';
        }
        $string .= '</dublin_core>';
        return $string;
    }

    function validate() {
        if (!isset($this->record['200']['A'])) {
            echo 'Помилка! Відсутній заголовок запису<br />';
            echo '<pre>';
            print_r($this->record);
            echo '</pre>';
            return false;
        }
        foreach ($this->required_fields as $key => $fields) {
            foreach ($fields as $subkey) {
                if (!isset($this->record[$key][$subkey])) {
                    echo 'Помилка! <br/>У записі з заголовком <u>' . $this->record['200']['A'] . '</u>';
                    echo '<br/>відсутне поле поле <b>' . $key . '-' . $subkey . '</b><br />';
                    return false;
                }
            }
        }
        return true;
    }

    function createSrc($dirname) {
        if (!$dirname && $this->validate()) {
            return false;
        }
        mkdir($this->srcFolder . '/' . $dirname);
        chmod($this->srcFolder . '/' . $dirname, 0775);
        //XML
        $this->writeFile($this->srcFolder . '/' . $dirname . '/dublin_core.xml', $this->xmlGenerate());

        //HTML
        if (isset($this->record[951]['I'])) {
            $htmlString = file_get_contents($this->record[951]['I']);
            $this->writeFile($this->srcFolder . '/' . $dirname . '/' . $dirname . '.html', $htmlString);
            $this->writeFile($this->srcFolder . '/' . $dirname . '/contents', $dirname . '.html	bundle:ORIGINAL');
            $this->writeFile($this->srcFolder . '/' . $dirname . '/contents', $dirname . '.html	bundle:ORIGINAL');
        }
        return true;
    }

    function writeFile($filePath, $fileString) {
        $handle = fopen($filePath, 'w');
        fwrite($handle, $fileString);
        fclose($handle);
        chmod($filePath, 0775);
    }

    function delSrc($dirname) {
        //chmod($this->srcFolder.'/'.$dirname, 0775);
        //foreach(scandir($this->srcFolder.'/'.$dirname) as $val){

        /*if ($val == '.' && $val == '..') continue;
          $val_path = $this->srcFolder.'/'.$dirname.'/'.$val;
        if (is_dir($val)){
          foreach(scandir($val_path) as $subval){
            if ($subval == '.' && $subval == '..') continue;
            unlink($val_path.'/'.$subval);
            }
          rmdir($val_path);
          } else unlink($val_path);
        */
        //}
        //rmdir($this->srcFolder.'/'.$dirname);
        return true;
    }

    function getBibOpys() {
        $string = '';
        //[700-A] [700-B] [200-A] [: [200-E] ]/ [200-F] // [463-C] [: [963-E] ][/ [963-F]][. - [463-D] ]: [463-G], [463-J].[ - [463-H].] - С. [463-S].
        //[700-A] [700-B]
        $string .= $this->record['700']['A'] . ' ' . $this->record['700']['B'] . ' ';
        //[200-A]
        $string .= $this->record['200']['A'] . ' ';
        //[: [200-E] ]/
        $string .= (isset($this->record['200']['E'])) ? (': ' . $this->record['200']['E'] . ' / ') : '/ ';
        //[200-F] //
        $string .= $this->record['200']['F'] . ' // ';
        //[463-C]
        $string .= $this->record['463']['C'] . ' ';
        //[: [963-E] ]
        $string .= (isset($this->record['963']['E'])) ? (': ' . $this->record['963']['E'] . ' ') : '';
        //[/ [963-F]]
        $string .= (isset($this->record['963']['F'])) ? ('/ ' . $this->record['963']['F']) : '';
        //[. - [463-D] ]
        $string .= (isset($this->record['463']['D'])) ? ('. - ' . $this->record['463']['D'] . ' : ') : ': ';

        //[463-G],
        $string .= $this->record['463']['G'] . ', ';
        //[463-J].
        $string .= $this->record['463']['J'] . '. ';
        //[ - [463-H].]
        $string .= (isset($this->record['463']['H'])) ? (' - ' . $this->record['463']['H'] . '.') : '';
        // - С. [463-S]
        $string .= ' - C. ' . $this->record['463']['S'] . '.';
        return $string;
    }

}

function xml2array($contents, $get_attributes = 1, $priority = 'tag') {
    if (!$contents) {
        return array();
    }

    if (!function_exists('xml_parser_create')) {
        //print "'xml_parser_create()' function not found!";
        return array();
    }

    //Get the XML parser of PHP - PHP must have this module for the parser to work
    $parser = xml_parser_create('');
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    // http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values) {
        return;
    }
    //Hmm...

    //Initializations
    $xml_array = array();
    $parents = array();
    $opened_tags = array();
    $arr = array();

    $current = &$xml_array; //Refference
    //Go through the tags.
    $repeated_tag_index = array();//Multiple tags with same name will be turned into an array
    foreach ($xml_values as $data) {
        unset($attributes, $value);//Remove existing values, or there will be trouble

        //This command will extract these variables into the foreach scope
        // tag(string), type(string), level(int), attributes(array).
        extract($data);//We could use the array by itself, but this cooler.

        $result = array();
        $attributes_data = array();

        if (isset($value)) {
            if ($priority == 'tag') {
                $result = $value;
            } else {
                $result['value'] = $value;
            }
            //Put the value in a assoc array if we are in the 'Attribute' mode
        }

        //Set the attributes too.
        if (isset($attributes) and $get_attributes) {
            foreach ($attributes as $attr => $val) {
                if ($priority == 'tag') {
                    $attributes_data[$attr] = $val;
                } else {
                    $result['attr'][$attr] = $val;
                }
                //Set all the attributes in a array called 'attr'
            }
        }

        //See tag status and do the needed.
        if ($type == "open") {
            //The starting of the tag '<tag>'
            $parent[$level - 1] = &$current;
            if (!is_array($current) or (!in_array($tag, array_keys($current)))) {
                //Insert New tag
                $current[$tag] = $result;
                if ($attributes_data) {
                    $current[$tag . '_attr'] = $attributes_data;
                }
                $repeated_tag_index[$tag . '_' . $level] = 1;

                $current = &$current[$tag];

            } else {
                //There was another element with the same tag name
                if (is_array($current[$tag]) && isset($current[$tag][0])) {
                    //If there is a 0th element it is already an array
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $repeated_tag_index[$tag . '_' . $level]++;
                } else {
                    //This section will make the value an array if multiple
                    // tags with the same name appear together
                    $current[$tag] = array($current[$tag], $result);
                    //This will combine the existing item and the new item together to make an array
                    $repeated_tag_index[$tag . '_' . $level] = 2;

                    if (isset($current[$tag . '_attr'])) { //The attribute of the last(0th) tag must be moved as well
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = &$current[$tag][$last_item_index];
            }
        } elseif ($type == "complete") { //Tags that ends in 1 line '<tag />'
            //See if the key is already taken.
            if (!isset($current[$tag])) { //New Key
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data) {
                    $current[$tag . '_attr'] = $attributes_data;
                }

            } else {
                //If taken, put all things inside a list(array)
                if (isset($current[$tag][0]) and is_array($current[$tag])) {
                    //If it is already an array...

                    // ...push the new element into that array.
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;

                    if ($priority == 'tag' and $get_attributes and $attributes_data) {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;

                } else {
                    //If it is not an array...
                    $current[$tag] = array($current[$tag], $result);
                    //...Make it an array using using the existing value and the new value
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes) {
                        if (is_array($current) && isset($current[$tag . '_attr'])) {
                        //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset($current[$tag . '_attr']);
                        }

                        if ($attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }

        } elseif ($type == 'close') { //End of tag '</tag>'
            $current = &$parent[$level - 1];
        }
    }

    return ($xml_array);
}
