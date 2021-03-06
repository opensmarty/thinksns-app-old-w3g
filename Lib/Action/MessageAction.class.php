<?php
/**
 * MessageAction 消息模块.
 *
 * @version TS3.0
 */
class MessageAction extends BaseAction
{
    /**
     * 模块初始化.
     */
    public function _initialize()
    {
    }

    /**
     * 私信列表.
     */
    public function index()
    {
        $dao = model('Message');
        $list = $dao->getMessageListByUid($this->mid, array(MessageModel::ONE_ON_ONE_CHAT, MessageModel::MULTIPLAYER_CHAT));
        // 设置信息已读(在右上角提示去掉),
        model('Message')->setMessageIsRead(t($POST['id']), $this->mid, 1);
        if ($list['nowPage'] <= 1 && is_array($list['data']) && isset($list['data'][0])) {
            $only = !isset($list['data'][1]) || $list['data'][1]['new'] <= 0;
            if ($list['data'][0]['new'] > 0 && $only) {
                $url = U('w3g/Message/detail', array(
                    'id' => $list['data'][0]['list_id'],
                ));
                $this->assign('jumpUrl', $url);
            }
        }
        $this->assign($list);
        // dump($list);
        $this->assign('count', $list['totalRows']);
        $this->assign('tpage', $list['totalPages']);
        $this->setTitle(L('PUBLIC_MESSAGE_INDEX'));
        $userInfo = model('User')->getUserInfo($this->mid);
        $this->setKeywords($userInfo['uname'].'的私信');
        $this->assign('headtitle', '私信列表');
        $this->display('list');
    }
    /**
     * 点击关闭信息按钮后设置当前用户所有信息为已读.
     */
    public function setAllIsRead()
    {
        if (!$this->mid) {
            $this->ajaxReturn(null, '操作失败', 0);
        }
        model('Message')->setAllIsRead($this->mid);
        model('UserCount')->resetUserCount($this->mid, 'unread_atme');
        model('UserCount')->resetUserCount($this->mid, 'unread_comment');
        model('Notify')->setRead($this->mid);
        $this->ajaxReturn(null, '操作成功', 1);
    }

    /**
     * 系统通知.
     */
    public function notify()
    {
        //$list = model('Notify')->getMessageList($this->mid);     //2012/12/27
        //下边这句是为了获取$count
        $limit = 20;
        $list = D('notify_message')->where('uid='.$this->mid)->order('ctime desc')->findpage($limit);
        $count = $list['count'];
        $this->assign('count', $count);
        $page = $_GET['page'] ? intval($_GET['page']) : 1;
        $start = ($page - 1) * $limit;
        $list = D('notify_message')->where('uid='.$this->mid)->order('ctime desc')->limit("$start,$limit")->select();
        foreach ($list as $k => $v) {
            $list[$k]['body'] = parse_html($v['body']);
            if ($appname != 'public') {
                $list[$k]['app'] = model('App')->getAppByName($v['appname']);
            }
        }
        model('Notify')->setRead($this->mid);
        $this->assign('page', $page);
        $this->assign('list', $list);
        $this->setTitle(L('PUBLIC_MESSAGE_NOTIFY'));
        $this->setKeywords(L('PUBLIC_MESSAGE_NOTIFY'));
        $this->assign('headtitle', '系统通知');
        $this->display('mynotify');
    }

    /**
     * 获取指定应用指定用户下的消息列表.
     */
    public function notifyDetail()
    {
        $appname = t($_REQUEST['appname']);
        //设置为已读
        //model('Notify')->setRead($this->mid,$appname);
        $this->assign('appname', $appname);
        if ($appname != 'public') {
            $appinfo = model('App')->getAppByName($appname);
            $this->assign('appinfo', $appinfo);
        }
        $list = model('Notify')->getMessageDetail($appname, $this->mid);
        $this->assign($list);
        $this->display();
    }

    /**
     * 删除私信
     */
    public function delnotify()
    {
        model('Notify')->deleteNotify(t($_REQUEST['id']));
    }

    /**
     * 私信详情.
     */
    public function detail()
    {
        $message = model('Message')->isMember(t($_GET['id']), $this->mid, true);
        $r = array();
        // 验证数据
        if (empty($message)) {
            $this->error(L('PUBLIC_PRI_MESSAGE_NOEXIST'));
        }
        $message['member'] = model('Message')->getMessageMembers(t($_GET['id']), 'member_uid');
        $message['to'] = array();
        // 添加发送用户ID
        foreach ($message['member'] as $index => $v) {
            $message['member'][$index]['user_info']['space_link'] = ''; //TODO:change link
            $message['member'][$index]['user_info']['space_link_no'] = ''; //TODO:change link
            $this->mid != $v['member_uid'] && $message['to'][] = $message['member'][$index];
        }
        $r['to'] = $message['to'];
        $r['member'] = $message['member'];
        // 设置信息已读(私信列表页去掉new标识)
        model('Message')->setMessageIsRead(t($_GET['id']), $this->mid, 0);
        $message['since_id'] = model('Message')->getSinceMessageId($message['list_id'], $message['message_num']);

        $this->assign('message', $message);
        $this->assign('type', intval($_GET['type']));

        $this->setTitle('与'.$message['to'][0]['user_info']['uname'].'的私信对话');
        $this->setKeywords('与'.$message['to'][0]['user_info']['uname'].'的私信对话');
        // dump($message);
        $this->assign('headtitle', '私信详情');
        $list_id = t($_GET['id']);
        $where = "`list_id`={$list_id} AND `is_del`=0";
        $limit = 20;
        $r['data'] = array_reverse(D('message_content')->where($where)->order('message_id DESC')->limit($limit)->findAll());
        //dump($r);exit;
        $this->assign('message', $message);
        $this->assign('r', $r);
        $profile = api('User')->data($data)->show();
        $this->assign('profile', $profile);
        $this->display();
//        echo json_encode($r);
    }

    /**
     * 获取指定私信列表中的私信内容.
     */
    public function loadMessage()
    {
        $message = model('Message')->getMessageByListId(intval($_POST['list_id']), $this->mid, intval($_POST['since_id']), intval($_POST['max_id']));
        // 临时解决方案
        foreach ($message['data'] as &$value) {
            if ($value['content'] == t($value['content'])) {
                $value['content'] = replaceUrl($value['content']);
            }
        }
        $this->assign('message', $message);
        $this->assign('type', intval($_POST['type']));
        $message['data'] = $message['data'] ? $this->fetch() : null;
        echo json_encode($message);
    }

    /**
     * 发送私信弹窗.
     */
    public function post()
    {
        $touid = t($_GET['touid']);
        $max = $_REQUEST['max'] ? intval($_REQUEST['max']) : 10;
        $this->assign('max', $max);
        $this->assign('touid', $touid);
        // 是否能够编辑用户
        $editable = intval($_REQUEST['editable']) == 0 ? 0 : 1;
        $this->assign('editable', $editable);

        $this->display();
    }

    /**
     * 发送私信
     */
    public function doPost()
    {
        $return = array('data' => L('PUBLIC_SEND_SUCCESS'), 'status' => 1);
        if (empty($_POST['to']) || !CheckPermission('core_normal', 'send_message')) {
            $return['data'] = L('PUBLIC_SYSTEM_MAIL_ISNOT');
            $return['status'] = 0;
            echo json_encode($return);
            exit();
        }
        if (trim(t($_POST['content'])) == '') {
            $return['data'] = L('PUBLIC_COMMENT_MAIL_REQUIRED');
            $return['status'] = 0;
            echo json_encode($return);
            exit();
        }
        $_POST['to'] = trim(t($_POST['to']), ',');
        $to_num = explode(',', $_POST['to']);
        if (count($to_num) > 10) {
            $return['data'] = '';
            $return['status'] = 0;
            echo json_encode($return);
            exit();
        }
        !in_array($_POST['type'], array(MessageModel::ONE_ON_ONE_CHAT, MessageModel::MULTIPLAYER_CHAT)) && $_POST['type'] = null;
        $_POST['content'] = h($_POST['content']);
        // 图片附件信息
        if (trim(t($_POST['attach_ids'])) != '') {
            $_POST['attach_ids'] = explode('|', $_POST['attach_ids']);
            $_POST['attach_ids'] = array_filter($_POST['attach_ids']);
            $_POST['attach_ids'] = array_unique($_POST['attach_ids']);
        }
        $res = model('Message')->postMessage($_POST, $this->mid);
        if ($res) {
            echo json_encode($return);
            exit();
        } else {
            $return['status'] = 0;
            $return['data'] = model('Message')->getError();
            echo json_encode($return);
            exit();
        }
    }

    /**
     * 回复私信
     */
    public function doReply()
    {
        $UserPrivacy = model('UserPrivacy')->getPrivacy($this->mid, intval($_POST['to']));
        if ($UserPrivacy['message'] != 0) {
            echo json_encode(array('status' => 0, 'data' => '根据对方的隐私设置，您无法给TA发送私信'));
            exit;
        }
        $_POST['reply_content'] = t($_POST['reply_content']);
        $_POST['id'] = intval($_POST['id']);

        if (!$_POST['id'] || empty($_POST['reply_content'])) {
            echo json_encode(array('status' => 0, 'data' => L('PUBLIC_COMMENT_MAIL_REQUIRED')));
            exit;
        }

        // 图片附件信息
        if (trim(t($_POST['attach_ids'])) != '') {
            $_POST['attach_ids'] = explode('|', $_POST['attach_ids']);
            $_POST['attach_ids'] = array_filter($_POST['attach_ids']);
            $_POST['attach_ids'] = array_unique($_POST['attach_ids']);
        }

        $res = model('Message')->replyMessage($_POST['id'], $_POST['reply_content'], $this->mid, $_POST['attach_ids']);
        if ($res) {
            echo json_encode(array('status' => 1, 'data' => L('PUBLIC_PRIVATE_MESSAGE_SEND_SUCCESS')));
        } else {
            echo json_encode(array('status' => 0, 'data' => L('PUBLIC_PRIVATE_MESSAGE_SEND_FAIL')));
        }
        exit();
    }

    /**
     * 设置指定私信为已读.
     *
     * @return int 1=成功 0=失败
     */
    public function doSetIsRead()
    {
        $res = model('Message')->setMessageIsRead(t($_POST['ids']), $this->mid);
        if ($res) {
            echo 1;
        } else {
            echo 0;
        }
    }

    /**
     * 删除私信
     *
     * @return int 1=成功 0=失败
     */
    public function doDelete()
    {
        $res = model('Message')->deleteMessageByListId($this->mid, t($_POST['ids']));
        if ($res) {
            echo 1;
        } else {
            echo 0;
        }
    }

    /**
     * 删除用户指定私信会话.
     *
     * @return int 1=成功 0=失败
     */
    public function doDeleteSession()
    {
        $res = model('Message')->deleteSessionById($this->mid, t($_POST['ids']));
        if ($res) {
            echo 1;
        } else {
            echo 0;
        }
    }

    public function doSendFeedMail()
    {
        //手动执行邮件任务
        model('Message')->doSendFeedMail();
    }
}
