<?php
/**
 * @brief 员工模块
 * @class Staff
 * @note  后台
 */
class Staff extends IController
{
	public $checkRight  = 'all';
    public $layout='admin';
	private $data = array();

	function init()
	{
		IInterceptor::reg('CheckRights@onCreateAction');
	}
	/**
	 * @brief 店月绩查询
	 */
	function staff_list()
	{
		$admin_id = ISafe::get('admin_id');
		$role_id = ISafe::get('role_id');
		if($role_id==2||$role_id==3){
			$this->redirect('staff_performance');
		}else if($role_id==4||$role_id==0){//经理查看各个店月绩查询
			$adminDB = new IQuery('admin as a');
			$adminDB->fields = 'a.id,a.admin_name,b.name';
			$adminDB->join = 'left join admin_role as b on a.role_id = b.id left join staff_relations as c on a.id = c.admin_id';
			$where = ' a.role_id = 3';
			if($role_id==4){
				$where .=' and c.parent_id = '.$admin_id;
			}
			$adminDB->where = $where;
			$adminInfo = $adminDB->find();
			$this->adminInfo = $adminInfo;
		}
		
		$this->redirect('staff_list');
	}
	/**
	 * @brief 员工详情
	 */
	function staff_detail()
	{
		$admin_id = ISafe::get('admin_id');
		if($admin_id)
		{
			$adminObj = new IModel('admin');
			$where = 'id = '.$admin_id;
			$this->adminRow = $adminObj->getObj($where);
		}
		$this->redirect('staff_detail');
	}
	//员工绩效查询
	function staff_performance()
	{
		
		$admin_id = ISafe::get('admin_id');
		$role_id = ISafe::get('role_id');
		
		$req_admin_id = IReq::get('admin_id');
		if(!empty($req_admin_id)){
			$this->calculate_performance($req_admin_id,3);
		}else{
			if($role_id==0||$role_id==4)
			{
				$this->redirect('staff_list');
			}
			$this->calculate_performance($admin_id,$role_id);
		}
		$this->redirect('staff_performance');
	}
	
	//计算员工下的绩效
	public function calculate_performance($admin_id=null,$role_id=2)
	{
		//主管绩效
		if($role_id==3)
		{
			//获取主管下的员工
			$staff_obj = new IModel('staff_relations');
			$where = 'parent_id='.$admin_id;
			$data_staff = $staff_obj->query($where);
			$admin_obj = new IModel('admin');
			$staff_no = array();
			if(!empty($data_staff)){
				foreach($data_staff as $v){
					//根据员工id查询员工号
					$where = 'id='.$v['admin_id'];
					$data_admin = $admin_obj->getObj($where);
					$staff_no[] = "'".$data_admin['admin_no']."'";
				}
				if(!empty($staff_no))
				{
					$id_str = join(",",$staff_no);
				}
				$where = ' a.user_id=b.id and b.id=c.user_id';
				if(!empty($id_str))
				{
					$where .= ' and c.admin_no in ('.$id_str.')';
					$this->init_count('collection_doc as a,user as b,member as c', 'sum(amount) as num,left(a.`time`,7) AS month',$where);
				}
			}

		}else if($role_id==2)//员工绩效
		{
			$admin_obj = new IModel('admin');
			$where = 'id='.$admin_id;
			$data_admin = $admin_obj->getObj($where);
			$where = ' a.user_id=b.id and b.id=c.user_id and c.admin_no="'.$data_admin['admin_no'].'"';
			$this->init_count('collection_doc as a,user as b,member as c', 'sum(amount) as num,left(a.`time`,7) AS month',$where);
		}
	}

	public function init_count($table, $fields,$where=null)
	{
		$start = IReq::get('start');
        $end = IReq::get('end');
        if($start == '') $start = date('Y').'-01-01';
        if($end  == '') $end  = date('Y').'-12-31';
        if(strcasecmp($start,$end)>0)$end = $start;
        $users = new IQuery($table);
        $users->fields = $fields;
		if($where){
			$users->where = $where.' and a.time >= \''.$start.' 00:00:00\' and a.time <=\''.$end.' 24:59:59\'';
        }else{
			$users->where = ' a.time >= \''.$start.' 00:00:00\' and a.time <=\''.$end.' 24:59:59\'';
		}
		$users->group = 'left(a.time,7)';
        $rs = $users->find();
        $numbers ='';
        $dates = '';
        $max = 0;
        foreach($rs as $row)
        {

            $numbers .= $row['num'].',';
            $dates .='"'.$row['month'].'月",';
            if($max<$row['num']) $max = $row['num'];
        }
        $this->steps = ceil($max/10);
        $this->numbers = strlen($numbers)>1 ? substr($numbers,0,-1):null;
        $this->dates = strlen($dates)>1 ? substr($dates,0,-1) : null;
        $this->max = $max+$this->steps;
	}
	//员工升级考核预约
	function staff_reservation()
	{
		$this->redirect('staff_reservation');
	}
	
	/**
	 * @brief 会员列表
	 */
	function member_list()
	{
		$search = IFilter::string(IReq::get('search'));
		$keywords = IFilter::string(IReq::get('keywords'));
		$where = ' 1 ';
		if($search && $keywords)
		{
			$where .= " and $search like '%{$keywords}%' ";
		}
		$this->data['search'] = $search;
		$this->data['keywords'] = $keywords;
		$this->data['where'] = $where;
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$group      = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
		}
		$this->data['group'] = $group;
		$this->setRenderData($this->data);
		$this->redirect('member_list');
	}

	/**
	 * 用户余额管理页面
	 */
	function member_balance()
	{
		$this->layout = '';
		$this->redirect('member_balance');
	}
	/**
	 * @brief 删除至回收站
	 */
	function member_reclaim()
	{
		$user_ids = IReq::get('check');
		$user_ids = is_array($user_ids) ? $user_ids : array($user_ids);
		$user_ids = IFilter::act($user_ids,'int');
		if($user_ids)
		{
			$ids = implode(',',$user_ids);
			if($ids)
			{
				$tb_member = new IModel('member');
				$tb_member->setData(array('status'=>'2'));
				$where = "user_id in (".$ids.")";
				$tb_member->update($where);
			}
		}
		$this->member_list();
	}
	//批量用户充值
    function member_recharge()
    {
    	$id = IReq::get('check');
    	$balance = IReq::get('balance');
    	$type = IReq::get('type');
    	$order_no = IFilter::act( IReq::get('order_no') );
    	$even = '';

    	if(!$id)
    	{
			echo JSON::encode(array('flag' => 'fail','message' => '请选择要操作的用户'));
			return;
    	}

    	if($type=='3')
    	{
    		$balance = '-'.abs($balance);
    		$even = 'withdraw';
    	}
    	else
    	{
    		$balance = abs($balance);
    		if($type=='1')
    		{
    			$even = 'recharge';
    		}
    		else
    		{
    			$even = 'drawback';
    			if(is_array($id) && count($id)>1)
    			{
    				echo JSON::encode(array('flag' => 'fail','message' => '订单退款功能不能批量处理'));
					return;
    			}
    			if(is_array($id))
    			{
    				$id = end($id);
    			}
    			$id = intval($id);
    			//检测这个订单是不是这个用户的，且是否申请退款了
    			$obj = new IModel("order");
    			$row = $obj->query("user_id={$id} AND order_no = '{$order_no}' and (pay_status = 1 or pay_status = 3)");
    			if(!$row)
    			{
    				echo JSON::encode(array('flag' => 'fail','message' => '不存在这个订单或付款状态不正确'));
					return;
    			}
    		}
    	}

		$obj = new IModel('member');
		if(is_array($id) && isset($id[0]) && $id[0]!='')
		{
			$id_str = join(',',$id);
			//按用户id数组查询出用户余额，然后进行充值
			$member_info = $obj->query('user_id in ('.$id_str.')');
			if(count($member_info)>0)
			{
				foreach ($member_info as $value)
				{
					$balance_bak = $value['balance']+$balance;
					if($balance_bak>=0)
					{
						$obj->setData(array('balance'=>$balance_bak));
						$obj->update('user_id = '.$value['user_id']);

						//用户余额进行的操作记入account_log表
						$log = new AccountLog();
						$config=array
						(
							'user_id'=>$value['user_id'],
							'admin_id'=>$this->admin['admin_id'], //如果需要的话
							'event'=>$even, //withdraw:提现,pay:余额支付,recharge:充值,drawback:退款到余额
							'num'=> $balance, //整形或者浮点，正为增加，负为减少
							'order_no' =>$order_no // drawback类型的log需要这个值
						);
						$re = $log->write($config);
					}
				}
			}
		}
		else
		{
			//按用户id数组查询出用户余额，然后进行充值
			$member_info = $obj->query('user_id = '.$id);
			if(count($member_info)>0)
			{
				$balance_bak = $member_info[0]['balance']+$balance;
				if($balance_bak>=0)
				{
					$obj->setData(array('balance'=>$balance_bak));
					$obj->update('user_id = '.$id);

					//用户余额进行的操作记入account_log表
					$log = new AccountLog();
					$config=array(
						'user_id'=>$id,
					 	'admin_id'=>$this->admin['admin_id'], //如果需要的话
					 	'event'=>$even, //withdraw:提现,pay:余额支付,recharge:充值,drawback:退款到余额
					 	'num'=> $balance, //整形或者浮点，正为增加，负为减少
					 	'order_no' =>$order_no // drawback类型的log需要这个值
					 );
					 $re = $log->write($config);
				}
			}
		}
		echo JSON::encode(array('flag' => 'success'));
		return;
    }
	/**
	 * @brief 用户组添加
	 */
	function group_edit()
	{
		$gid = (int)IReq::get('gid');
		//编辑会员等级信息 读取会员等级信息
		if($gid)
		{
			$tb_user_group = new IModel('user_group');
			$group_info = $tb_user_group->query("id=".$gid);

			if(is_array($group_info) && ($info=$group_info[0]))
			{
				$this->data['group'] = array(
					'group_id'	=>	$info['id'],
					'group_name'=>	$info['group_name'],
					'discount'	=>	$info['discount'],
					'minexp'	=>	$info['minexp'],
					'maxexp'	=>	$info['maxexp']
				);
			}
			else
			{
				$this->redirect('group_list',false);
				Util::showMessage("没有找到相关记录！");
				return;
			}
		}
		$this->setRenderData($this->data);
		$this->redirect('group_edit');
	}

	/**
	 * @brief 保存用户组修改
	 */
	function group_save()
	{
		$group_id = IFilter::act(IReq::get('group_id'),'int');
		$maxexp   = IFilter::act(IReq::get('maxexp'),'int');
		$minexp   = IFilter::act(IReq::get('minexp'),'int');
		$discount = IFilter::act(IReq::get('discount'),'float');
		$group_name = IFilter::act(IReq::get('group_name'));

		$group = array(
			'maxexp' => $maxexp,
			'minexp' => $minexp,
			'discount' => $discount,
			'group_name' => $group_name
		);

		if($discount > 100)
		{
			$errorMsg = '折扣率不能大于100';
		}

		if($maxexp <= $minexp)
		{
			$errorMsg = '最大经验值必须大于最小经验值';
		}

		if(isset($errorMsg) && $errorMsg)
		{
			$group['group_id'] = $group_id;
			$data = array($group);

			$this->setRenderData($data);
			$this->redirect('group_edit',false);
			Util::showMessage($errorMsg);
			exit;
		}
		$tb_user_group = new IModel("user_group");
		$tb_user_group->setData($group);

		if($group_id)
		{
			$affected_rows = $tb_user_group->update("id=".$group_id);
			if($affected_rows)
			{
				$this->redirect('group_list',false);
				Util::showMessage('更新用户组成功！');
				return;
			}
			$this->redirect('group_list',false);
		}
		else
		{
			$gid = $tb_user_group->add();
			$this->redirect('group_list',false);
			if($gid)
			{
				Util::showMessage('添加用户组成功！');
			}
			else
			{
				Util::showMessage('添加用户组失败！');
			}
		}
	}

	/**
	 * @brief 删除会员组
	 */
	function group_del()
	{
		$group_ids = IReq::get('check');
		$group_ids = is_array($group_ids) ? $group_ids : array($group_ids);
		$group_ids = IFilter::act($group_ids,'int');
		if($group_ids)
		{
			$ids = implode(',',$group_ids);
			if($ids)
			{
				$tb_user_group = new IModel('user_group');
				$where = "id in (".$ids.")";
				$tb_user_group->del($where);
			}
		}
		$this->redirect('group_list');
	}

	/**
	 * @brief 回收站
	 */
	function recycling()
	{
		$search = IReq::get('search');
		$keywords = IReq::get('keywords');
		$search_sql = IFilter::act($search,'string');
		$keywords = IFilter::act($keywords,'string');

		$where = ' 1 ';
		if($search && $keywords)
		{
			$where .= " and $search_sql like '%{$keywords_sql}%' ";
		}
		$this->data['search'] = $search;
		$this->data['keywords'] = $keywords;
		$this->data['where'] = $where;
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$data_group = is_array($data_group) ? $data_group : array();
		$group = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
		}
		$this->data['group'] = $group;
		$this->setRenderData($this->data);
		$this->redirect('recycling');
	}

	/**
	 * @brief 彻底删除会员
	 */
	function member_del()
	{
		$user_ids = IReq::get('check');
		$user_ids = is_array($user_ids) ? $user_ids : array($user_ids);
		$user_ids = IFilter::act($user_ids,'int');
		if($user_ids)
		{
			$ids = implode(',',$user_ids);

			if($ids)
			{
				$tb_member = new IModel('member');
				$where = "user_id in (".$ids.")";
				$tb_member->del($where);

				$tb_user = new IModel('user');
				$where = "id in (".$ids.")";
				$tb_user->del($where);

				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"删除了用户","被删除的用户ID为：".$ids));
			}
		}
		$this->redirect('member_list');
	}

	/**
	 * @brief 从回收站还原会员
	 */
	function member_restore()
	{
		$user_ids = IReq::get('check');
		$user_ids = is_array($user_ids) ? $user_ids : array($user_ids);
		if($user_ids)
		{
			$user_ids = IFilter::act($user_ids,'int');
			$ids = implode(',',$user_ids);
			if($ids)
			{
				$tb_member = new IModel('member');
				$tb_member->setData(array('status'=>'1'));
				$where = "user_id in (".$ids.")";
				$tb_member->update($where);
			}
		}
		$this->redirect('recycling');
	}

	//[提现管理] 删除
	function withdraw_del()
	{
		$id   = IReq::get('id');

		if(!empty($id))
		{
			$id = IFilter::act($id,'int');
			$withdrawObj = new IModel('withdraw');

			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}

			$withdrawObj->del($where);
			$this->redirect('withdraw_recycle');
		}
		else
		{
			$this->redirect('withdraw_recycle',false);
			Util::showMessage('请选择要删除的数据');
		}
	}

	//[提现管理] 回收站 删除,恢复
	function withdraw_update()
	{
		$id   = IFilter::act( IReq::get('id') , 'int' );
		$type = IReq::get('type') ;

		if(!empty($id))
		{
			$withdrawObj = new IModel('withdraw');

			$is_del = ($type == 'res') ? '0' : '1';
			$dataArray = array(
				'is_del' => $is_del
			);

			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = ' id in ('.$idStr.')';
			}
			else
			{
				$where = 'id = '.$id;
			}

			$dataArray = array(
				'is_del' => $is_del,
			);

			$withdrawObj->setData($dataArray);
			$withdrawObj->update($where);
			$this->redirect('withdraw_list');
		}
		else
		{
			if($type == 'del')
			{
				$this->redirect('withdraw_list',false);
			}
			else
			{
				$this->redirect('withdraw_recycle',false);
			}
			Util::showMessage('请选择要删除的数据');
		}
	}

	//[提现管理] 详情展示
	function withdraw_detail()
	{
		$id = IFilter::act( IReq::get('id'),'int' );

		if($id)
		{
			$withdrawObj = new IModel('withdraw');
			$where       = 'id = '.$id;
			$this->withdrawRow = $withdrawObj->getObj($where);
			$this->redirect('withdraw_detail',false);
		}
		else
		{
			$this->redirect('withdraw_list');
		}
	}

	//[提现管理] 修改提现申请的状态
	function withdraw_status()
	{
		$id      = IFilter::act( IReq::get('id'),'int' );
		$re_note = IFilter::act( IReq::get('re_note'),'string' );

		if($id)
		{
			$withdrawObj = new IModel('withdraw');
			$dataArray = array(
				're_note'=> $re_note,
			);
			if(IReq::get('status') !== NULL)
			{
				$dataArray['status'] = IFilter::act(IReq::get('status') , 'int');
			}

			$withdrawObj->setData($dataArray);
			$where = "`id`= {$id} AND `status` = 0";
			$re = $withdrawObj->update($where);
			$this->withdraw_detail(true);

			if($re != 0)
			{
				$logObj = new log('db');
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改了提现申请","ID值为：".$id));
			}

			Util::showMessage("更新成功");
		}
		else
		{
			$this->redirect('withdraw_list');
		}
	}

}
