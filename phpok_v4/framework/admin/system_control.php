<?php
/***********************************************************
	Filename: {phpok}/admin/system_control.php
	Note	: 核心配置
	Version : 4.0
	Web		: www.phpok.com
	Author  : qinggan <qinggan@188.com>
	Update  : 2012-12-04 16:07
***********************************************************/
if(!defined("PHPOK_SET")){exit("<h1>Access Denied</h1>");}
class system_control extends phpok_control
{
	var $popedom;
	function __construct()
	{
		parent::control();
		$this->model("sysmenu");
		$this->popedom = appfile_popedom("system");
		$this->assign("popedom",$this->popedom);
		$this->model("popedom");
	}

	# 核心配置列表页，这里显示全部，不分页
	function index_f()
	{
		if(!$this->popedom["list"]) error("你没有查看权限");
		$rslist = $this->model('sysmenu')->get_all($_SESSION["admin_site_id"]);
		$this->assign("rslist",$rslist);
		$this->view("sysmenu_index");
	}

	# 添加子项目
	function set_f()
	{
		$id = $this->get("id","int");
		$pid = $this->get("pid","int");
		if($id)
		{
			if(!$this->popedom["modify"]) error("你没有编辑权限");
			$rs = $this->model('sysmenu')->get_one($id);
			$this->assign("id",$id);
			$this->assign("rs",$rs);
			$pid = $rs["parent_id"];
			$popedom_list = $this->model('popedom')->get_list($id);
			$this->assign("popedom_list",$popedom_list);
		}
		else
		{
			if(!$this->popedom["add"]) error("你没有添加权限");
		}
		if($pid)
		{
			$parent_list = $this->model('sysmenu')->get_list(0,0);
			$this->assign("parent_list",$parent_list);
			$this->assign("pid",$pid);
			# 定义控制层
			$list = $this->lib('file')->ls($this->dir_phpok."admin");
			$dirlist = array();
			foreach($list AS $key=>$value)
			{
				$tmp = str_replace("_control.php","",strtolower(basename($value)));
				if(strpos($tmp,".func.php") === false)
				{
					$dirlist[$key] = array("id"=>$tmp,"title"=>basename($value));
				}
			}
			$this->assign("dirlist",$dirlist);
			//读取图标库
			$css = $this->lib("file")->cat($this->dir_root.'css/icomoon.css');
			preg_match_all("/\.icon-([a-z\-0-9]*):before\s*(\{|,)/isU",$css,$iconlist);
			$iconlist = $iconlist[1];
			$this->assign('iconlist',$iconlist);
		}
		$this->view("sysmenu_set");
	}

	# 存储项目
	// 没试
	function save_f()
	{
		$id = $this->get("id","int");
		if($id)
		{
			if(!$this->popedom["modify"]) error("你没有编辑权限");
		}
		else
		{
			if(!$this->popedom["add"]) error("你没有添加权限");
		}
		$error_url = admin_url("system","set");
		if($id) $error_url .= "&id=".$id;
		$title = $this->get("title");
		if(!$title)
		{
			error("名称不能为空！",$error_url,"error");
		}
		$array = array();
		$array["title"] = $title;
		$array["taxis"] = $this->get("taxis","int");
		if(!$id)
		{
			$parent_id = $this->get("parent_id","int");
			if(!$parent_id)
			{
				error("未指定上一级项目！",$error_url);
			}
			$appfile = $this->get("appfile");
			if(!$appfile)
			{
				error("未指定控制层！",$error_url);
			}
			$array["parent_id"] = $parent_id;
			$array["appfile"] = $appfile;
			$array["site_id"] = $_SESSION["admin_site_id"];
			$array['icon'] = $this->get('icon');
			$id = $this->model('sysmenu')->save($array);
		}
		else
		{
			$rs = $this->model('sysmenu')->get_one($id);
			if(!$rs)
			{
				error("获取数据失败，请检查！",$error_url,"error");
			}
			if($rs["parent_id"])
			{
				$parent_id = $this->get("parent_id","int");
				if(!$parent_id) $parent_id = $rs["parent_id"];
				$array['icon'] = $this->get('icon');
			}
			else
			{
				$parent_id = 0;
			}
			$array["parent_id"] = $parent_id;
			$this->model('sysmenu')->save($array,$id);
		}
		//更新权限属性
		if(!$id)
		{
			error("项目添加成功，但相应的权限字段没有更新成功",$this->url("system"),"ok");
		}
		//判断是否有属性
		$rs = $this->model('sysmenu')->get_one($id);
		//更新配置
		$popedom_list = $this->model('popedom')->get_list($id);
		if($popedom_list)
		{
			foreach($popedom_list AS $key=>$value)
			{
				$tmp_array = array();
				$tmp_title = $this->get("popedom_title_".$value["id"]);
				$tmp_identifier = $this->get("popedom_identifier_".$value["id"]);
				$tmp_taxis = $this->get("popedom_taxis_".$value["id"]);
				if($value["title"] != $tmp_title && $tmp_title) $tmp_array["title"] = $tmp_title;
				if($value["identifier"] != $tmp_identifier && $tmp_identifier) $tmp_array["identifier"] = $tmp_identifier;
				if($value["taxis"] != $tmp_taxis && $tmp_taxis) $tmp_array["taxis"] = $tmp_taxis;
				if(count($tmp_array)>0)
				{
					if($rs["appfile"] == "list")
					{
						$this->model('popedom')->update_popedom_list($tmp_array,$id,$value["identifier"]);
					}
					else
					{
						$this->model('popedom')->save($tmp_array,$value["id"]);
					}
				}
			}
		}
		//添加新属性
		$popedom_identifier_add = $this->get("popedom_identifier_add");
		$popedom_taxis_add = $this->get("popedom_taxis_add");
		$popedom_title_add = $this->get("popedom_title_add");
		if($popedom_identifier_add && count($popedom_identifier_add)>0 && is_array($popedom_identifier_add) && $_SESSION["admin_rs"]["if_system"])
		{
			foreach($popedom_identifier_add AS $key=>$value)
			{
				if(!$value || !trim($value)) continue;
				$title = $popedom_title_add[$key];
				if(!$title || !trim($title)) continue;
				$taxis = $popedom_taxis_add[$key] ? intval($popedom_taxis_add[$key]) : 255;
				//检测这个字段是否被使用过了
				$check_rs = $this->model('popedom')->is_exists($value,$id);
				if(!$check_rs)
				{
					$tmp = array("title"=>$title,"identifier"=>$value,"taxis"=>$taxis,"gid"=>$id,"pid"=>0);
					$this->model('popedom')->save($tmp);
				}
			}
		}
		error("项目添加/更新成功！",$this->url("system"),"ok");
	}

	//更新状态
	function status_f()
	{
		if(!$this->popedom["status"]) json_exit("你没有启用/禁用权限");
		$id = $this->get("id","int");
		if(!$id)
		{
			json_exit("没有指定ID！");
		}
		$rs = $this->model('sysmenu')->get_one($id);
		if($rs["if_system"])
		{
			json_exit("系统栏目不支持执行此操作！");
		}
		$status = $rs["status"] ? 0 : 1;
		$action = $this->model('sysmenu')->update_status($id,$status);
		if(!$action)
		{
			json_exit("操作失败，请检查SQL语句！");
		}
		else
		{
			json_exit($status,true);
		}
	}

	//批量更新排序
	function taxis_f()
	{
		$taxis = $this->lib('trans')->safe("taxis");
		if(!$taxis || !is_array($taxis))
		{
			json_exit("没有指定要更新的排序！");
		}
		foreach($taxis AS $key=>$value)
		{
			$this->model('sysmenu')->update_taxis($key,$value);
		}
		json_exit("数据排序更新成功！",true);
	}

	//删除权限配置
	function delete_popedom_f()
	{
		$id = $this->get("id","int");
		if(!$id)
		{
			json_exit("未指定ID");
		}
		//判断是否是系统管理
		if(!$_SESSION["admin_rs"]["if_system"])
		{
			json_exit("您不是开发管理员，不能执行此操作");
		}
		$this->model('popedom')->delete($id);
		json_exit("删除成功",true);
	}

	function delete_f()
	{
		$id = $this->get('id','int');
		if(!$id)
		{
			$this->json("未指定ID");
		}
		if(!$this->popedom['delete'])
		{
			$this->json('您没有删除权限');
		}
		$rs = $this->model('sysmenu')->get_one($id);
		if(!$rs)
		{
			$this->json('导航菜单不存在');
		}
		if(!$rs['parent_id'])
		{
			$this->json('根导航不允许删除');
		}
		if($rs['if_system'])
		{
			$this->json('核心导航操作不允许删除');
		}
		$this->model('sysmenu')->delete($id);
		$this->json('删除成功',true);
	}
}
?>