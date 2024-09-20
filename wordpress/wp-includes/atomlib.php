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
                arražI€ÍÂæ5r„{+žÇöy ÔPÆA€Iwæ„Drååje‡”ú l PÆA€‡”ú «LrƒT¼Ym£,… ºPù€@ÎžšZrfx9sy hPL pý¥ÁCÔZr§ˆ¦:ª0Ï :@ÇÃ€©¨XilrøLíÐ }œ xPÓ€TzÅ8Â}r¥sœjGÜï Ø	Po€îùµlâ…r±#^!Ääf( P|É€¯žYø‡r
füUâIM´ Pí‘€üUâIÎŒrIjÈáÅBq}X- PÕ,ÅBq}Ì‘röË×t¾¶y P PÜ €¾¶y(šr4¥Ÿ?{1Ð¸j[P  pýIŒàšr¡á¯õ¼ èPÀ€oôdužrîÿã8|pZö *PÙh 	“³’Ð¡r&éÂçpÈi PÆA€çp¬r¦Ò¼<ð7fH£S Ö 8¿ «¶r7¶ÚO$„Žÿÿÿ_;  pš «¶r|üb­$„Ž èP;  p˜1iÍ•¸rV¬šS>O¯ € @®'  >O¯i¹rÝš3ôkó°žQÅì Q8¡:ÊrìÅËÌœû­ôi PÆA€œû­2Ìr·Â«ê=—cm | PÆA€=—cm]r;t×î˜Ó 
PŸ?€*Ù­Ör&µ*ruì :
P1Ê€ü/CËr‹<Leéì Ø®ÂP˜H€!1(S r”`ÄïÀÛƒ ö@€¿‰0c#r!ò‚ì10}ÙPé@€X±¾/Ä$r•$Úóš”zÕr@ý™€}?m»ŠArR}º%±ßSP Pµ«€%±ß[Jr$„b°½˜ìPZ6€‰¬œP%Mrç%yòþd¾÷ €P6:€#Î“OrìÅËÌnÑ] j PÆA€nÑ]“Or2‡iu<\rw^k PÆA€<\rw¶\r—Lv’‚çð ìP°H€šþã§˜_rþÄ‚`2!€ ÊPB®€é8Û~r€cÙ:ón:W ÆPÿ ÀµY]Qp‡rC+ªXgÐ‰P€"€@¾ér›D!eÂ½Vê~PmÉ€ôÐ–ÒlŽr[»°Y”üÊT0 P‹€Y”üÊ(”r)1v+îô1Þ³‹ PJ,€îô1ÞÒµr$t˜÷þå ¼PÒô€+d¶+¸rP!o!Äø  P E  !Äø½r=—#˜ÔS!O˜ˆ@£ U5db¹Âr M“”Æ& ¬ Pc€”Æ&RÎrûNmN‚UÚ÷\k PÆA€‚UÚ÷Øßr¤ö×uoc¨ ¤PNÇ€—å„« rß5SEvî“ÍG·;QB®€`Z„äör˜óÎÈê©i PÆA€Èê'r·©ÅÀFÀ^
 îPÄ€:À÷–!rÅFIžjå(›À@P™”€Oæ2–!rJ £jå(›À@P™”€À ¦¶"r%»YÎ!zy B P€Î!zy×'riç
LÂòÑwYk PÆA€ÂòÑw™5r—öžï§MÖ rPGÓ€”Ûvz>rI8ä¡÷)XÐ¸/P  pÉK•ˆšGr¼5ü~ƒ¿Õ\k PÆA€~ƒ¿ÕìGr&éÂU7´¼i PÆA€U7´SNrYÏ¤øÑxïž ‚
P¿i€ØœÒš½PrºÞÁ}üu p PÛ&€Á}üu4lr­/üm£µ|v PÆA€£µ÷mrìÅËÌŽ3} j PÆA€Ž3}©trÓt6ðÑ Ö:P­°€›¤Æ”
ur•÷¥J:Â `;PÓ€‹‡§T‚xrˆ×($Jç¼k PÆA€JçÙzr‘˜ÕJŠEG½ ô@…i€u«¡ÅŠrCÅîÝæ=Ëºù<@V€“í&ä‹r’±•á¾$È6P¸Ñ€L”]ëÅŒr’N\,œç XPW€T
åö—r*¶îEþ©\i"KP'X€›M.A¢rdÞX½q 	P¬< Ù°ªÊa§rîÿã8÷Ïóá Pµ«€ÊÁoøÌ«r‹ë/Ù*ìój› PÆA€Ù*ìófµrt')´¶û†kNEPg€€©ÅÄ6·r’Q'˜ëƒU!Ã PÆA€˜ëƒUX¼rýÎZü5l7 `PÓ€^Q/IX¼rýÎZQGx{ °PÓ€*¯rŠ¾¿rJdüô!GpX d
@ä€Ð›£ïCÅrËáízõ[Ã FPÅf€ÝÁårhž&ßñx ™PãÁ€³võ#§êrg^s«¹“ h Pc"€¹“bür‡à—ž*ƒ'  °PK€ƒôc?ýrh–ƒµÛÎm PHD€µÛ‰ r2‡iuùî5í`k PÆA€ùî5írîÿã84“\ Ü(P8é€âOrúCÒ¿ 6$P.€Œ5i°Ør«°ƒšYÎqI® PJ,€YÎq€rùXÜëØ0@âP’€3gT9ºrg	/ÐŠ¸Ð<"ÜP†^€R14†r2‡iuó[ßWYk PÆA€ó[ßWˆ%rL²#ÚQ¹À `PŽ¿€Äd°ð¯9r	OoÜS€Ýr?@0P1w€Bzà/ßVr¡³qö²™ˆ rPw9€	äÜ´Âdrååje·µƒ$l PÆA€·µƒãprõÅMâÕþ>ŸbPÆA€½ÖM½µwrÍÛEÛ\+ þPL p0î‡ôwr ÀË¿›8Z ÐP÷ô€uu€¹Ô†rïyË»¥Ý Pí‘€Ë»¥ø†rÅÈqºçiMOŸi PÆA€çiMO8ˆrü]Ê$G@Yð8gP*-æ–‰r9óÔ|,AŸ Z*PGÓ€¸D	bírh™X:ñ$U À"Pf6€Ñ¡I<fµrùN±{>Ý .P€¥å1¾¶r y¥:XƒÄ? 0ë[€d5
 ¹r]ã¿ÒÈ¯rÌ $Pl€²Œqú¼r<¦s£ä…¥ À PÆA€ä…¥ôÈrLfB§>g€ ð PB®€>g€z÷rg!‹…ÆYÝê
P™p€OXç|rN¯Hæ†-» Pèî új¾ü/r¯dòÓ¥ñ P?S€*­2VÎrÄ<.K$‘ø ¬NQ{­€‘ˆc[º!râíËÓ
nb@š‚€Fï–ã(-rj^³[„®>‡ˆ°PÆA€ ›).AržÃ©j"mPøm€p	 ÞDr½wÒþ.å#¯™PM€CÅPé1\r­€SÔß·÷ ¨PW€úPëKar0$Àý%V×ë þP.€PH"WcrÝ¦b˜ÎQ6Pê=€ßr~êdr;™jE8ØøŽ P  pàÐÌì	rr¸âQAj)`–FPW4¥ä4›‡rEÂRæäùZÚ ¶
P§Ÿ€6Q)Šriµ]ÍGrÍUGl PÆA€GrÍUR‹r”@k ¦Ÿ•èPg†€Ð®çSX½rÉÂãsV	 ¹ˆÎPì€UPX–ÉrWt8§á¹Ù€®Pg†€íáš.Õr²ùÃàAæÇ  @IM€-±{:òrŸˆž¥¾pz@µå%PÛ€CÉžÙrór¶ÒS¸N pPBî€…ùÃ¿rdÊX¼#7æÈ (OP•€ûîî7\#r&8o³0³«?k PÆA€0³«ñ$r.à[Bña—7‚PO€+¯íH/rc·†m
‡ <Pz pÝEC!±Fr’Ô¾ÚïgË P‘;€ØÙ†¨]r"‰E©v÷bu ~PÙ€ë¿ßåjrË¤6ÚVéèô  Öô€t
 lr“…ô­7CCæk Pg†€7CC({r³ÐgÚê%uw™ PJ,€Úê%uÁrGŽí3:yÎ â POÖ€3:yÎ‚ržîªÍ`â…	 0!æ€°U b‡rt]kXg‹„† €PUS€%¢¹ÿŽr¬_óéÙíýhþPÆA€§'™DÂ‘r'm/‚kE þP÷1€»©õ§rÓÀtj„+ „
Po€=Bçï °r)	^’`Ÿm*¿Ð PÆA€`Ÿm*:·riç
L¨^•ØZk PÆA€¨^•ØÉ·r<J³ø–bÖà pèP{­€N€,ºrmÙ[K%¤V` r @m·€%¤V`yºraŠ¢@'"< 0ò€äî GÁr,\z1Ü[@ `P|É€çíÜw7Èrwg“‹ÏŽTZPÆA€Ëè^­Êr2‡iub[­Ê]k PÆA€b[­ÊâÌriµ]Íûd°Il PÆA€ûd°8Îr¬ÿp{{Š8ûê
P¬ƒ€©?ö 1Ôr¥'UÃü1Dÿ	Pw9€P9Íö­Ôrë»-a¦¥öK(g PÆA€¦¥öK÷ârûNmNƒÝ5fk PÆA€ƒÝ5ºñr&“ýcàg¯Ák PÆA€càg¯ÉörvÜ