<?php

namespace ZippyERP\System\Pages;

use Zippy\Binding\PropertyBinding;
use Zippy\Html\Label;
use Zippy\Html\Panel;
use Zippy\Html\Link\ClickLink;
use ZippyERP\ERP\Helper;
use ZippyERP\System\Application as App;
use ZippyERP\System\System;
use ZippyERP\System\User;

class Base extends \Zippy\Html\WebPage
{

    public $_errormsg;
    public $_warnmsg;
    public $_successmsg;

    public function __construct($params = null)
    {

        \Zippy\Html\WebPage::__construct();


        if ($_COOKIE['remember'] && System::getUser()->user_id == 0) {
            $arr = explode('_', $_COOKIE['remember']);
            $_config = parse_ini_file(_ROOT . 'config/config.ini', true);
            if ($arr[0] > 0 && $arr[1] === md5($arr[0] . $_config['common']['salt'])) {
                $user = User::load($arr[0]);
            }

            if ($user instanceof User) {


                System::setUser($user);

                $_SESSION['user_id'] = $user->user_id; //для  использования  вне  Application
                $_SESSION['userlogin'] = $user->userlogin; //для  использования  вне  Application
                //   @mkdir(_ROOT . UPLOAD_USERS .$user->user_id) ;
                //  \ZippyERP\System\Util::removeDirRec(_ROOT . UPLOAD_USERS .$user->user_id.'/tmp') ;
                //   @mkdir(_ROOT .UPLOAD_USERS .$user->user_id .'/tmp') ;
            }
        }


        $user = System::getUser();
        if ($user->user_id == 0) {
            App::Redirect("\\ZippyERP\\System\\Pages\\Userlogin");
        }

        $this->add(new ClickLink('logout', $this, 'LogoutClick'));
        $this->add(new Label('username', $user->userlogin));



        $this->add(new \Zippy\Html\Label("warnmessage", new \Zippy\Binding\PropertyBinding($this, '_warnmsg'), false, true))->setVisible(false);
        $this->add(new \Zippy\Html\Label("successmessage", new \Zippy\Binding\PropertyBinding($this, '_successmsg'), false, true))->setVisible(false);
        $this->add(new \Zippy\Html\Label("errormessage", new PropertyBinding($this, '_errormsg')))->setVisible(false);

        $this->add(new ClickLink("pageinfo"));

        $pi = $this->getPageInfo();
        $this->add(new Label("picontent", $pi));
        if (strlen($pi) == 0) {
            $this->pageinfo->setVisible(false);
        }

        $this->add(new Label("docmenu", Helper::generateMenu(1), true));
        $this->add(new Label("repmenu", Helper::generateMenu(2), true));
        $this->add(new Label("regmenu", Helper::generateMenu(3), true));
        $this->add(new Label("refmenu", Helper::generateMenu(4), true));
        $this->add(new Label("pagemenu", Helper::generateMenu(5), true));

        $this->_tvars["islogined"] = $user->user_id > 0;
        $this->_tvars["isadmin"] = $user->userlogin == 'admin';
    }

    public function LogoutClick($sender)
    {
        setcookie("remember", '', 0);
        System::setUser(new \ZippyERP\System\User());
        $_SESSION['user_id'] = 0;
        $_SESSION['userlogin'] = 'Гость';

        //$page = $this->getOwnerPage();
        //  $page = get_class($page)  ;
        App::RedirectHome();
        ;
        ;
        //    App::$app->getresponse()->toBack();
    }

    public function getPageInfo()
    {
        $class = explode("\\", get_class($this));
        $classname = $class[count($class) - 1];
        return \ZippyERP\ERP\Helper::getMetaNotes($classname);
    }

    //вывод ошибки,  используется   в дочерних страницах
    final protected function setError($msg)
    {
        $this->_errormsg = $msg;
    }

    public function setWarn($msg)
    {
        $this->_warnmsg = $msg;
        $this->warnmessage->setVisible(true);
    }

    public function setSuccess($msg)
    {
        $this->_successmsg = $msg;
        $this->successmessage->setVisible(true);
    }

    final protected function isError()
    {
        return strlen($this->_errormsg) > 0;
    }

    protected function beforeRender()
    {
        $this->errormessage->setVisible(strlen($this->_errormsg) > 0);
    }

    protected function afterRender()
    {
        $this->errormessage->setVisible(false);
        $this->warnmessage->setVisible(false);
        $this->successmessage->setVisible(false);
    }

}
