<?php
/**
 * Atom Syndication Format PHP Library
 *
 * @package AtomLib
 * @link http://code.google.com/p/phpatomlib/
 *
 * @author Elias Torres <elias@torrez.us>
 * @version 0.4
 * @since 2.3.0
 */

/**
 * Structure that store common Atom Feed Properties
 *
 * @package AtomLib
 */
class AtomFeed {
	/**
	 * Stores Links
	 * @var array
	 * @access public
	 */
    var $links = array();
    /**
     * Stores Categories
     * @var array
     * @access public
     */
    var $categories = array();
	/**
	 * Stores Entries
	 *
	 * @var array
	 * @access public
	 */
    var $entries = array();
}

/**
 * Structure that store Atom Entry Properties
 *
 * @package AtomLib
 */
class AtomEntry {
	/**
	 * Stores Links
	 * @var array
	 * @access public
	 */
    var $links = array();
    /**
     * Stores Categories
     * @var array
	 * @access public
     */
    var $categories = array();
}

/**
 * AtomLib Atom Parser API
 *
 * @package AtomLib
 */
class AtomParser {

    var $NS = 'http://www.w3.org/2005/Atom';
    var $ATOM_CONTENT_ELEMENTS = array('content','summary','title','subtitle','rights');
    var $ATOM_SIMPLE_ELEMENTS = array('id','updated','published','draft');

    var $debug = false;

    var $depth = 0;
    var $indent = 2;
    var $in_content;
    var $ns_contexts = array();
    var $ns_decls = array();
    var $content_ns_decls = array();
    var $content_ns_contexts = array();
    var $is_xhtml = false;
    var $is_html = false;
    var $is_text = true;
    var $skipped_div = false;

    var $FILE = "php://input";

    var $feed;
    var $current;

	/**
	 * PHP5 constructor.
	 */
    function __construct() {

        $this->feed = new AtomFeed();
        $this->current = null;
        $this->map_attrs_func = array( __CLASS__, 'map_attrs' );
        $this->map_xmlns_func = array( __CLASS__, 'map_xmlns' );
    }

	/**
	 * PHP4 constructor.
	 */
	public function AtomParser() {
		self::__construct();
	}

	/**
	 * Map attributes to key="val"
	 *
	 * @param string $k Key
	 * @param string $v Value
	 * @return string
	 */
	public static function map_attrs($k, $v) {
		return "$k=\"$v\"";
	}

	/**
	 * Map XML namespace to string.
	 *
	 * @param indexish $p XML Namespace element index
	 * @param array $n Two-element array pair. [ 0 => {namespace}, 1 => {url} ]
	 * @return string 'xmlns="{url}"' or 'xmlns:{namespace}="{url}"'
	 */
	public static function map_xmlns($p, $n) {
		$xd = "xmlns";
		if( 0 < strlen($n[0]) ) {
			$xd .= ":{$n[0]}";
		}
		return "{$xd}=\"{$n[1]}\"";
	}

    function _p($msg) {
        if($this->debug) {
            print str_repeat(" ", $this->depth * $this->indent) . $msg ."\n";
        }
    }

    function error_handler($log_level, $log_text, $error_file, $error_line) {
        $this->error = $log_text;
    }

    function parse() {

        set_error_handler(array(&$this, 'error_handler'));

        array_unshift($this->ns_contexts, array());

        if ( ! function_exists( 'xml_parser_create_ns' ) ) {
        	trigger_error( __( "PHP's XML extension is not available. Please contact your hosting provider to enable PHP's XML extension." ) );
        	return false;
        }

        $parser = xml_parser_create_ns();
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, "start_element", "end_element");
        xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
        xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
        xml_set_character_data_handler($parser, "cdata");
        xml_set_default_handler($parser, "_default");
        xml_set_start_namespace_decl_handler($parser, "start_ns");
        xml_set_end_namespace_decl_handler($parser, "end_ns");

        $this->content = '';

        $ret = true;

        $fp = fopen($this->FILE, "r");
        while ($data = fread($fp, 4096)) {
            if($this->debug) $this->content .= $data;

            if(!xml_parse($parser, $data, feof($fp))) {
                /* translators: 1: Error message, 2: Line number. */
                trigger_error(sprintf(__('XML Error: %1$s at line %2$s')."\n",
                    xml_error_string(xml_get_error_code($parser)),
                    xml_get_current_line_number($parser)));
                $ret = false;
                break;
            }
        }
        fclose($fp);

        xml_parser_free($parser);
        unset($parser);

        restore_error_handler();

        return $ret;
    }

    function start_element($parser, $name, $attrs) {

        $name_parts = explode(":", $name);
        $tag        = array_pop($name_parts);

        switch($name) {
            case $this->NS . ':feed':
                $this->current = $this->feed;
                break;
            case $this->NS . ':entry':
                $this->current = new AtomEntry();
                break;
        };

        $this->_p("start_element('$name')");
        #$this->_p(print_r($this->ns_contexts,true));
        #$this->_p('current(' . $this->current . ')');

        array_unshift($this->ns_contexts, $this->ns_decls);

        $this->depth++;

        if(!empty($this->in_content)) {

            $this->content_ns_decls = array();

            if($this->is_html || $this->is_text)
                trigger_error("Invalid content in element found. Content must not be of type text or html if it contains markup.");

            $attrs_prefix = array();

            // resolve prefixes for attributes
            foreach($attrs as $key => $value) {
                $with_prefix = $this->ns_to_prefix($key, true);
                $attrs_prefix[$with_prefix[1]] = $this->xml_escape($value);
            }

            $attrs_str = join(' ', array_map($this->map_attrs_func, array_keys($attrs_prefix), array_values($attrs_prefix)));
            if(strlen($attrs_str) > 0) {
                $attrs_str = " " . $attrs_str;
            }

            $with_prefix = $this->ns_to_prefix($name);

            if(!$this->is_declared_content_ns($with_prefix[0])) {
                array_push($this->content_ns_decls, $with_prefix[0]);
            }

            $xmlns_str = '';
            if(count($this->content_ns_decls) > 0) {
                array_unshift($this->content_ns_contexts, $this->content_ns_decls);
                $xmlns_str .= join(' ', array_map($this->map_xmlns_func, array_keys($this->content_ns_contexts[0]), array_values($this->content_ns_contexts[0])));
                if(strlen($xmlns_str) > 0) {
                    $xmlns_str = " " . $xmlns_str;
                }
            }

            array_push($this->in_content, array($tag, $this->depth, "<". $with_prefix[1] ."{$xmlns_str}{$attrs_str}" . ">"));

        } else if(in_array($tag, $this->ATOM_CONTENT_ELEMENTS) || in_array($tag, $this->ATOM_SIMPLE_ELEMENTS)) {
            $this->in_content = array();
            $this->is_xhtml = $attrs['type'] == 'xhtml';
            $this->is_html = $attrs['type'] == 'html' || $attrs['type'] == 'text/html';
            $this->is_text = !in_array('type',array_keys($attrs)) || $attrs['type'] == 'text';
            $type = $this->is_xhtml ? 'XHTML' : ($this->is_html ? 'HTML' : ($this->is_text ? 'TEXT' : $attrs['type']));

            if(in_array('src',array_keys($attrs))) {
                $this->current->$tag = $attrs;
            } else {
                array_push($this->in_content, array($tag,$this->depth, $type));
            }
        } else if($tag == 'link') {
            array_push($this->current->links, $attrs);
        } else if($tag == 'category') {
            array_push($this->current->categories, $attrs);
        }

        $this->ns_decls = array();
    }

    function end_element($parser, $name) {

        $name_parts = explode(":", $name);
        $tag        = array_pop($name_parts);

        $ccount = count($this->in_content);

        # if we are *in* content, then let's proceed to serialize it
        if(!empty($this->in_content)) {
            # if we are ending the original content element
            # then let's finalize the content
            if($this->in_content[0][0] == $tag &&
                $this->in_content[0][1] == $this->depth) {
                $origtype = $this->in_content[0][2];
                arra�I����5r�{+���y �P�A�Iw�Dr��je��� l P�A���� �Lr�T�Ym�,� �P��@Ξ�Zrfx9sy hPL p���C�Zr���:�0� :@�����Xilr�L�� }� xP��Tz�8�}r�s�jG�� �	Po����l�r�#^!��f( P|����Y��r
f�U�IM� P���U�IΌrIj���Bq}X- P�,�Bq}̑r���t��y P P� ���y(�r4��?{1иj[P  p�I���r���� �P���o�du��r���8|pZ� *P�h 	���Сr&����p�i P�A��p�r��Ҽ<�7fH�S � 8� ��r7��O$�����_;  p�� ��r|�b�$�� �P;  p�1i͕�rV��S�>O� � @�'  �>O�i�rݚ3�k���Q�� Q8�:�r���̜���i P�A����2�r�«�=�cm | P�A�=�cm]r;t���� 
P�?�*���r&�*ru� :
P1���/C�r�<Le�� خ�P�H�!1(S r�`���ۃ �@���0c#r!��10}�P�@�X��/�$r�$�󁚔z�r@���}?m��ArR}�%���SP P���%���[Jr$��b����PZ6����P%Mr�%y��d�� �P6:�#��Or����n�] j P�A�n�]�Or2�iu<\rw^k P�A�<\rw�\r�Lv���� �P�H���㧘_r�Ă`2!� �PB���8�~r�c�:�n:W �P� ��Y]Qp�rC+��XgЉP�"�@���r�D!e½Vꐍ~Pm���Ж�l�r[���Y���T0 P��Y���(�r)1v+��1޳� PJ,���1�ҵr$t���� �P���+d�+�r�P!o!��  P E  !����r=�#��S!O��@�� U5db��r�M���& � P�c���&R�r�NmN�U��\k P�A��U����r���uoc� �PN����� r�5SEv��G�;QB��`Z���r������i P�A���'r����F�^
 �P��:���!r�FI�j�(��@P���O�2�!rJ��j�(��@P���� ��"r%�Y�!zy B P��!zy�'ri�
L���wYk P�A����w�5r����M� rPG����vz>rI8��)Xи/P  p�K���Gr�5�~���\k P�A�~����Gr&���U7��i P�A�U7�SNrYϤ��x� �
P�i�؜Қ�Pr���}�u p P�&��}�u4lr�/�m���|v P�A�����mr���̎3} j P�A��3}�trӐt�6�� �:P�����Ɣ
ur���J:�� `;P�����T�xr��($J�k P�A�J��zr���J�EG� �@�i�u����rC����=˺�<@V���&�r����$�6P���L�]�Ōr�N\,�� XPW�T
���r*��E��\i"KP'X��M.A�rd�X�q 	P�< ٰ��a�r���8���� P�����o�̫r��/�*��j� P�A��*��f�rt')�����kNEPg�����6�r�Q'��U!� P�A���UX�r��Z�5l7 `P��^Q/IX�r��ZQGx{ �P��*�r���rJd��!GpX d
@��Л��C�r���z�[� FP�f�݁��rh�&��x �P����v�#��rg^s��� h Pc"���b�r����*�'  �PK���c?�rh�������m PHD���ۉ r2�iu��5�`k P�A���5�r���84�\ �(P8����Or��Cҿ 6$P.��5i��r����Y�qI� PJ,�Y�q�r�X���0@�P���3gT9�rg	/Њ��<"�P�^�R14�r2�iu�[�WYk P�A��[�W�%rL�#�Q�� `P����d��9r	Oo�S��r?@0P1w�Bz�/�Vr��q���� rPw9�	�ܴ�dr��je���$l P�A�����pr��M���>�bP�A���M��wr��E�\+ �PL p0��wr �˿�8Z �P���uu��Ԇr�y˻��� P��˻���r��q��iMO�i P�A��iMO8�r�]�$G@Y�8gP�*-��r9��|,A�� Z*PG���D	b�rh�X:�$U �"Pf6�ѡI<f�r�N�{>� .P���1��r y�:�X��? 0�[�d5
 �r]��ȯr� $Pl���q��r<�s���� � P�A������rLfB�>g� � PB��>g�z�rg!���Y��
P�p�OX�|rN�H�-� P�� �j��/r�d�ӥ� P?S�*�2V�r�<.K$�� �NQ{����c[�!r����
nb@���F��(-rj^�[��>���P�A� �).Ar�Í�j"mP�m�p	��Dr�w��.�#��P�M�C�P�1\r��S�߷� �PW��P�Kar0$��%V�� ��P.�PH"Wcr���b��Q6P�=��r�~�dr;�jE8��� P  p����	rr��QAj)`�FPW4��4��rE�R���Z� �
P���6Q)�ri�]�Gr�UGl P�A�Gr�UR�r�@�k ����Pg��Ю�SX�r���sV	����P��UPX��rWt8��ـ�Pg����.�r��Ð�A��  @IM�-�{:�r�����pz@��%P��Cɞ�r�r��S�N pPB����ÿrd�X�#7�� (OP�����7\#r&8o�0��?k P�A�0���$r.�[B�a�7�PO�+��H/rc��m
� <�Pz p�EC!�Fr�Ծ��g� P�;��ٍ��]r"�E�v�bu ~P�����jrˤ6�V���  ���t
 lr�����7CC�k Pg���7CC({r��g��%uw� PJ,���%u��rG��3:y� � PO��3:y��r�����`�	 0!���U b�rt]kXg��� �PUS�%����r�_�����h�P�A��'�Dr'�m/�kE �P�1�����r���tj�+ �
Po�=B�� �r)	^�`�m*�� P�A�`�m*:�ri�
L�^��Zk P�A��^��ɷr<J���b�� p�P{��N�,�rm�[K%�V` r @m��%�V`y�ra��@'"< 0���� G�r,\z1�[@ `P|�����w7�rwg����TZP�A���^��r2�iub[��]k P�A�b[����ri�]��d�Il P�A��d�8�r��p{{�8��
P����?��1�r��'U��1D�	Pw9�P9����r�-a���K(g P�A����K��r�NmN��5fk P�A���5��r�&��c�g��k P�A�c�g���rv�