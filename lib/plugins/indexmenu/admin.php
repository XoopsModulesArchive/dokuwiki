<?php
/**
 * Indexmenu Admin Plugin:   Indexmenu Component.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Samuele Tognini <samuele@netsons.org>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once (DOKU_INC.'inc/HTTPClient.php'); 
require_once(DOKU_PLUGIN."indexmenu/inc/pclzip.lib.php");
if(!defined('INDEXMENU_IMG_ABSDIR')) define('INDEXMENU_IMG_ABSDIR',DOKU_PLUGIN."indexmenu/images");
define('INDEXMENU_ICOS','base,folder,folderopen,folderh,folderhopen,page,plus,minus,nolines_plus,nolines_minus,minusbottom,plusbottom,join,joinbottom,line,empty');

class admin_plugin_indexmenu extends DokuWiki_Admin_Plugin {
  var $req = 'fetch';
  var $repos = array (
		      "url"  => array(DOKU_URL),
		      "status" => array(""),
		      );

  var $selected=-1;

  /**
   * return some info
   */
  function getInfo(){
    return array(
		 'author' => 'Samuele Tognini',
		 'email'  => 'samuele@netsons.org',
		 'date'   => rtrim(io_readFile(DOKU_PLUGIN.'indexmenu/VERSION.txt')),
		 'name'   => 'Indexmenu (admin plugin component)',
		 'desc'   => 'Indexmenu admin functions.',
		 'url'    => 'http://wiki.splitbrain.org/plugin:indexmenu',
		 );
  }
 
  /**
   * return sort order for position in admin menu
   */
  function getMenuSort() {
    return 999;
  }
 
  /**
   * handle user request
   */
  function handle() {
    $url=$this->getConf('themes_url');
    if (empty($url)) {
      $this->repos['url'][]=$this->getLang('no_repos');
      $this->repos['status'][]="disabled";
      $this->repos['install'][]=-1;
    } else {
      $this->repos['url']= array_merge($this->repos['url'],explode(',',$url));
    }

    if (!isset($_REQUEST['req'])) return;   // first time - nothing to do
    $this->req=$_REQUEST['req'];

    if (isset($_REQUEST['repo']) && is_numeric($_REQUEST['repo'])) $this->selected=$_REQUEST['repo'];
  }
 
  /**
   * output appropriate html
   */
  function html() {
    global $conf;
    ptln('<div id="config__manager"');
    ptln(' <h1>'.$this->getLang('menu').'</h1>');
    ptln(' <fieldset>');
    ptln('   <legend>'.$this->getLang('checkupdates').'</legend>');
    ptln('   <table class="inline">');
    ptln('    <tr class="default"><td>');
    $this->_form_open("checkupdates");
    $this->_form_close('check');
    ptln('    </td></tr>');
    if ($this->req=='checkupdates') {
      ptln('    <tr class="default"><td>');
      $this->_checkupdates();
      ptln('    </td></tr>');
    } 

    ptln('   </table>');
    ptln(' </fieldset>');
    ptln(' <fieldset>');
    ptln('   <legend>Themes</legend>');
    ptln('   <table class="inline">');
    ptln('   <tr class="default"><td class="label" colspan=2>');
    ptln('   <span class="outkey">'.$this->getLang('infos').'</span>');
    ptln('   </td></tr>');
    $n=0;
    //cycles thru repositories urls
    foreach ($this->repos['url'] as $url) {
      ptln('    <tr class="search_hit"><td>');
      $legend=($n == 0) ? $conf['title'] : $this->repos['url'][$n];
      ptln('     <span><label><b>'.$legend.'</b></label></span>');
      ptln('    </td>');
      ptln('    <td class="value">');
      $this->_form_open("fetch",$n);
      $this->_form_close("fetch");

      //list requested theme
      if ($n==$this->selected) {
	ptln('    <tr class="default"><td colspan=2>');
	if ($this->req=='install') $this->install($this->selected,$_REQUEST['name']);
	if ($this->req=='upload' && $_REQUEST['name']) {
	  $info="";
	  if (isset($_REQUEST['author_info'])) $info .= "author=".$_REQUEST['author_info']."\n";
	  if (isset($_REQUEST['url_info'])) $info .= "url=".$_REQUEST['url_info']."\n";
	  if (isset($_REQUEST['author_info'])) $info .= "description=".$_REQUEST['author_info'];
	  if (!$this->upload($_REQUEST['name'],$info)) msg($this->getLang('install_no'),-1);
	}
	if ($this->req=='delete' && $_REQUEST['name']) $this->_delete($_REQUEST['name']);
	ptln('    </td></tr>');
	$this->dolist($n);
      }
      $n++;
    }
    ptln('   </table>');
    ptln('  </fieldset>');
    ptln('</div>');
  }

  /**
   * Connect to theme repository and list themes
   * 
   * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
   * @author     Samuele Tognini <samuele@netsons.org>
   */
  function dolist($n) {
    global $INFO;
    if ($n === false) return;
    //info.txt keys to parse
    $keys=array('author','url','description');
    $icos=explode(',',INDEXMENU_ICOS);
    $turl="";
    $info="";
    $obfuscate = array('@' => ' [at] ', '.' => ' [dot] ', '-' => ' [dash] ');
    ptln('    <tr><td colspan=2>');
    //get list
    $data=$this->_remotequery($this->repos['url'][$n]."/lib/plugins/indexmenu/ajax.php?req=local");
    $data=explode(",",$data);
    //print themes
    for ($i=3;$i<count($data);$i++) {
      $theme=$data[$i];
      $turl=$data[1].$data[2]."/".$theme;
      ptln('     <i>'.$theme.'</i>');
      ptln('    <div class="indexmenu_list_themes">');
      ptln('     <div>');
      //print images
      foreach (array_slice($icos,0,8) as $ico) {
	$ext = explode(".", $theme);
	$ext=array_pop($ext);
	$ext=($ext == $theme) ? '.gif' : ".$ext";
	ptln('      <img src="'.$turl."/".$ico.$ext.'" alt="" />');
      }
      ptln('      </div>');
      ptln("      <p>");
      //get theme info.txt
      if ($info=$this->_remotequery($turl."/info.txt",false)) {
	foreach ($keys as $key) {
	  if (!preg_match('/'.$key.'=(.*)/',$info,$out)) continue;
	  ptln("       <div>");
	  ptln("       <b>".hsc($key).": </b>".hsc($out[1]));
	  ptln("       </div>");
	}
      }
      if ($n == 0) {
	$act="upload";
	if ($theme != "default" ) {
	  $this->_form_open("delete",$n);
	  ptln('       <input type="hidden" name="name" value="'.$theme.'" />');
	  $this->_form_close("delete");
	}
      } else {
	$act="install";
	ptln('      <a href="'.$this->repos['url'][$n]."$repo/lib/plugins/indexmenu/ajax.php?req=send&t=".$theme.'">Download</a>');	
      }
      $this->_form_open($act,$n);
      if ($n ==0 && !$info) {
	ptln('       <div><b>Author:</b><input type="text" name="author_info" value="'.$INFO["userinfo"]["name"]." <".strtr($INFO["userinfo"]["mail"], $obfuscate).'>" size=50 maxlenght=100 /><br>');
	ptln('       <b>Url:</b><input type="text" name="url_info" value="'.$this->repos['url'][$n].'" size=50 maxlenght=200 /></div>');
	ptln('       <b>Description:</b><input type="text" name="description_info" value="" size=50 maxlenght=200 /></div>');
      }
      ptln('       <input type="hidden" name="name" value="'.$theme.'" />');
      $this->_form_close($act);
      ptln('     <p></div>');
    }
    ptln('    </td></tr>');
  }

  /**
   * Download and install themes
   * 
   * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
   * @author     Samuele Tognini <samuele@netsons.org>
   */
  function install ($n,$name) {
    $repo=$this->repos['url'][$n];
    if (!isset($name)) return false;
    $return=true;
    if (!$absdir=$this->checktmpsubdir()) return;
    $tmp=$absdir."/tmp";

    //send theme list request
    if (!$zipfile=io_download($repo."/lib/plugins/indexmenu/ajax.php?req=send&t=".$name,"$tmp/",true)) {
      msg($this->getLang('down_err').": $name",-1);
      $return=false;
    } else {
      //create zip
      $zip=new PclZip("$tmp/$zipfile");
      $regexp="/^".$name."\/(info.txt)|(style.css)|(".str_replace(",","|",INDEXMENU_ICOS).")\.(gif|png|jpg)$/i";
      $status=$zip->extract(PCLZIP_OPT_PATH,$absdir."/",PCLZIP_OPT_BY_PREG,$regexp);
      //error
      if ($status == 0) {
	msg($this->getLang('zip_err')." $tmp/$zipfile: ".$zip->errorName(true),-1);
	$return=false;
      } else {
	msg("<b>$name</b> ".$this->getLang('install_ok'),1);
      }
    }
    //clean tmp
    $this->_rm_dir($tmp);
    return $return;
  } 


  /**
   * Remove a directory
   * 
   */
  function _rm_dir($path) {    
    if (!is_string($path) || $path == "") return false;
    
    if (is_dir($path)) {
      if (!$dh = @opendir($path)) return false;
      
      while ($f = readdir($dh)) {
	if ($f == '..' || $f == '.') continue;
	$this->_rm_dir("$path/$f");
      }
      
      closedir($dh);
      return @rmdir($path);
    } else {
      return @unlink($path);
    }
    
    return false;
  }

  /**
   * Retrive and create themes tmp directory
   * 
   * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
   * @author     Samuele Tognini <samuele@netsons.org>
   */
  function checktmpsubdir () {
    $tmp=INDEXMENU_IMG_ABSDIR."/tmp";
    if (!io_mkdir_p($tmp)) {
      msg($this->getLang('dir_err').": $tmp",-1);
      return false;
    }
    return INDEXMENU_IMG_ABSDIR;
  }


  /**
   * Upload a theme into my site
   * 
   * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
   * @author     Samuele Tognini <samuele@netsons.org>
   */
  function upload($theme,$info) {
    $return=true;
    $host='samuele.netsons.org';
    $path='/dokuwiki/lib/plugins/indexmenu/upload/index.php';
    //TODO: merge zip creation with that in ajax.php (create a class?)
    if (!$absdir=$this->checktmpsubdir()) return false;
    $tmp=$absdir."/tmp";
    $zipfile="$theme.zip";
    $filelist="$absdir/$theme";
    //create info
    if (!empty($info)) {
      io_savefile("$tmp/$theme/info.txt",$info);
      $filelist .= ",$tmp/$theme";
    }      
    //create zip
    $zip=new PclZip("$tmp/$zipfile");
    $status=$zip->create($filelist,PCLZIP_OPT_REMOVE_ALL_PATH);
    if ($status == 0) {
      //error
      msg($this->getLang('zip_err').": ".$zip->errorName(true),-1);
      $return=false;
    } else {
      //prepare POST headers.
      $boundary = "---------------------------".uniqid("");
      $data=join("", file("$tmp/$zipfile"));
      $header="POST $path HTTP/1.0\r\n";
      $header.= "Host: $host\r\n";
      $header.= "User-Agent: Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1) Gecko/20061024 Iceweasel/2.0 (Debian-2.0+dfsg-1)\r\n";
      $header.= "Content-type: multipart/form-data, boundary=$boundary\r\n";
      $body="--".$boundary."\r\n";
      $body.= "Content-Disposition: form-data; name=\"userfile\"; filename=\"$zipfile\"\r\n";
      $body.= "Content-Type: application/x-zip-compressed\r\n\r\n";
      $body.=$data."\r\n";
      $body.= "--".$boundary."\r\n";
      $body.= "Content-Disposition: form-data; name=\"upload\"\r\n\r\n";
      $body.= "Upload\r\n";
      $body.="--".$boundary."--\r\n";
      $header.= "Content-Length: ".strlen($body)."\r\n\r\n";
      
      //connect and send zip
      if ($fp = fsockopen($host, 80)) {
	fwrite($fp,$header.$body);
	//reply
	$buf="";
	while (!feof($fp)) {
	  $buf .= fgets($fp,3200);
	}
	fclose($fp);
	//parse resply
	if (preg_match("/<!--indexmenu-->(.*)<!--\/indexmenu-->/s",$buf,$match)) {
	  $str=substr($match[1],4,7);
	  switch ($str) {
	  case "ERROR  ":
	    $mesg_type=-1;
	    break;
	  case "SUCCESS":
	    $mesg_type=1;
	    break;
	  default:
	    $mesg_type=2;
	  }
	  msg($match[1],$mesg_type);
	} else { 
	  $return=false;
	}
      } else {
	$return=false;
      }
    }

    $this->_rm_dir($tmp);
    return $return;
  }
  
  /**
   * Check for new messages from upstream
   *
   * @author Andreas Gohr <andi@splitbrain.org>
   * modified by Samuele Tognini <samuele@netsons.org>
   */
  function _checkupdates() {
    require_once (DOKU_INC.'inc/HTTPClient.php'); 
    global $conf;
    global $INFO;

    $date=$this->getInfo('date');
    $date=$date['date'];
    $data=$this->_remotequery("http://samuele.netsons.org/dokuwiki/lib/plugins/indexmenu/remote.php?check=$date");
    if ($data==="") msg($this->getLang('noupdates'),1);
    // show messages through the usual message mechanism
    $msgs = explode("\n%\n",$data);
    foreach($msgs as $msg){
      if($msg) msg($msg,-1);
    }

  }
  
  
  /**
   * Get url response and check it
   *
   * @author Samuele Tognini <samuele@netsons.org>
   */
  function _remotequery($url,$tag=true) {
    require_once (DOKU_INC.'inc/HTTPClient.php'); 
    $http = new DokuHTTPClient();
    $http->timeout = 8;
    $data = $http->get($url);
    if ($tag) {
      if ($data===false) {
	msg($this->getLang('conn_err'),-1);
      } else {
	(substr($data,0,9)==="indexmenu") ? $data=substr($data,9): $data="";
      }
    }
    return $data;
  }

  
  /**
   * Open an html form
   *
   * @author Samuele Tognini <samuele@netsons.org>
   */
  function _form_open($act,$n=0) {
    ptln('     <form action="'.wl($ID).'" method="post">');
    ptln('      <input type="hidden" name="do" value="admin" />');
    ptln('      <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
    ptln('      <input type="hidden" name="req" value="'.$act.'" />');
    ptln('      <input type="hidden" name="repo" value="'.$n.'" />');
  }

  /**
   * Close the html form
   *
   * @author Samuele Tognini <samuele@netsons.org>
   */
  function _form_close($act) {
    ptln('      <input type="submit" name="btn" '.$this->repos['status'][$n].' value="'.$this->getLang($act).'" />');
    ptln('     </form>');
  }

  /**
   * Remove an installed theme
   *
   * @author Samuele Tognini <samuele@netsons.org>
   */
  function _delete($theme) {
    if ($theme=="default") return;
    if ($this->_rm_dir(INDEXMENU_IMG_ABSDIR."/".utf8_encodeFN(basename($theme)))) {
      msg($this->getLang('delete_ok').": $theme.",1);
    } else {
      msg($this->getLang('delete_no').": $theme.",-1);
    }
  }
}