<?php 
defined("BASEPATH") or exit ("No direct script access allowed");

class Admin extends CI_Controller{
	
	public function __construct(){
		parent::__construct();
		$this->load->model(['admin/admin_model']);
	}

	public function loadview($loadview, $data=null){
		$this->load->view('admin/common/header',$data);
		$this->load->view('admin/'.$loadview);
		$this->load->view('admin/common/footer');
	}

	public function dashboard_loadview($loadview,$data=NULL){
		$admin_id=$this->session->userdata('admin_id');
        $admin['unseen_notification_count'] = $this->admin_model->getAdminUnseenNotification();
        $admin['notification'] = $this->admin_model->getAdminNotification();
        $i = 0;
        foreach ($admin['notification'] as $key => $value) {
            $user_image = $this->admin_model->getUserImage($value['user_id']);
            $admin['notification'][$i]['time'] = convertToHoursMinsSec(date('Y-m-d H:i:s', strtotime($value['created_at'])));
            if(!empty($user_image['image_url'])){
                $admin['notification'][$i]['image_url'] = base_url('uploads/profilePic/'.$user_image['image_url']);
            }else{
                $admin['notification'][$i]['image_url'] = base_url('assets/admin/no_image_avail.png');
            }
            $i++;
        }
		$data['admin_detail']=$this->admin_model->getAdminDetail($admin_id);
		$this->load->view('admin/common/header',$data);
		$this->load->view('admin/common/sidebar',$data);
		$this->load->view('admin/'.$loadview);
		$this->load->view('admin/common/footer');
    }

    private  function is_login(){
		$admin_id=$this->session->userdata('admin_id');
		if(empty($admin_id)){
			redirect('admin');
		}else{
			return $admin_id;
		}
    }

    //----------------------------- Upload single file-----------------------------

	public function doUploadImage($path,$file_name) {
        $config = array(
            'upload_path'   => $path,
            'allowed_types' => "jpeg|jpg|png|ico|svg",
            'file_name'     => rand(11111, 99999),
            'max_size'      => "5072"
        );
        $this->load->library('upload', $config);
        $this->upload->initialize($config);
        if ($this->upload->do_upload($file_name)) {
            $data = $this->upload->data();
            return $data['file_name'];
        } else {
            return $this->upload->display_errors();
        }
    }

    //----------------------------- Upload multiple files-----------------------------

    public function upload_files($path,$file_name){
        $this->output->set_content_type('application/json');
        $files = $_FILES[$file_name];
        $config = array(
            'upload_path'   => $path,
            'allowed_types' => 'jpeg|jpg|gif|png|pdf',
            'overwrite'     => 1,                       
        );
        $this->load->library('upload', $config);
        $images = array();
        $i=0;
        foreach ($files['name'] as $key => $image) {
            $_FILES['images[]']['name']= $files['name'][$key];
            $_FILES['images[]']['type']= $files['type'][$key];
            $_FILES['images[]']['tmp_name']= $files['tmp_name'][$key];
            $_FILES['images[]']['error']= $files['error'][$key];
            $_FILES['images[]']['size']= $files['size'][$key];

            $title = rand('1111','9999');
            $image = explode('.',$image);
            $count = count($image);
            $extension = $image[$count-1];
            $fileName = $title .'.'. $extension;
            $images[$i] = $fileName;
            $config['file_name'] = $fileName;
            $this->upload->initialize($config);

            if ($this->upload->do_upload('images[]')) {
                $this->upload->data();
            } else {
                return $this->upload->display_errors();
            }
            $i++;
        }
        return $images;
    }

	public function index(){
		if(!empty($this->session->userdata('admin_id'))){
			redirect('admin/dashboard');
		}
		$data['title'] = 'Admin Login';
		$data['admin_detail']=$this->admin_model->getAdminDetail(1);
		$this->load->view('admin/login', $data);
	}

	public function check_login(){
		$this->output->set_content_type('application/json');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->checkLogin();
        if ($result) {
            $this->session->set_userdata('admin_id', $result['id']);
            $this->output->set_output(json_encode(['result' => 3, 'url' => base_url('admin/dashboard'), 'msg' => 'Loading!! Please Wait...']));
            return FALSE;
        } else {
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Invalid username or password']));
            return FALSE;
		}
	}

    public function forgot_password(){
        $this->output->set_content_type('application/json');
        $email = $this->input->post('email');
        $admin_detail = $this->admin_model->get_admin_by_email($email);
        if(!empty($admin_detail)){
            $this->send_password_reset_mail($admin_detail);
            $this->admin_model->forgetPasswordLinkValidity($admin_detail['id']);
            $this->output->set_output(json_encode(['result' => 1, 'msg'=>'Reset Password Link Sent To Your Email Id.','url'=> base_url('admin')]));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg'=>'Please Enter Valid Email Id.']));
            return FALSE;
        }
    }
    
    public function send_password_reset_mail($admin_detail){
        $encrypted_id = encryptId($admin_detail['id']);
        $htmlContent = "<h3>Dear " . $admin_detail['name'] . ",</h3>";
        $htmlContent .= "<div style='padding-top:8px;'>Please click the following link to reset your password.</div>";
        $htmlContent .= "<a href='" . base_url('admin/reset-password/' . $encrypted_id) . "'> Click Here!!</a>";
        $from = "admin@health.com";
        $to = $admin_detail['email'];
        $subject = "[Helth Fitness] Forgot Password";
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        @mail($to, $subject, $htmlContent, $headers);
        return FALSE;
    }
    
    public function reset_password($id){
        $admin_id = decryptId($id);
        $data['admin_detail'] = $this->admin_model->getAdminDetail($admin_id);
        $data['title'] = "Reset Password";        
        $data['admin_id'] = $admin_id;
        $forget_password = $this->admin_model->getLinkValidity($admin_id);
        if($forget_password['status'] == 1){
            $data['forget_password'] = 'expired';
        }else{
            $data['forget_password'] = 'valid';
        }
        $this->admin_model->linkValidity($admin_id);
        $this->load->view('admin/reset_password',$data);
        
    }
    
    public function do_reset_password(){
        $this->output->set_content_type('application/json');
        $this->form_validation->set_rules('new_password', 'New Password', 'required');
        $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[new_password]');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $encrypted_id = $this->input->post('admin_id');
        $result = $this->admin_model->do_fogot_password($encrypted_id);
        if (!empty($result)) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin'), 'msg' => 'Pasword Reset Successfully']));
            return FALSE;
        } else {
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'New Password Cannot Be Same As Old Password.']));
            return FALSE;
        }
    }

	public function logout(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->session->unset_userdata('admin_id');
        $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin')]));
        return FALSE;
	}

	public function dashboard(){
		$this->is_login();
		$data['title'] = 'Admin Dashboard';    
        $data['user_count'] = count($this->admin_model->getUsersCount());
		$this->dashboard_loadview('dashboard', $data);
	}

    public function profile(){
        $admin_id=$this->is_login();
        $data['title']='Profile';
        $data['admin_detail']=$this->admin_model->getAdminDetail($admin_id);
        $this->dashboard_loadview('profile',$data);
    }

    public function updateProfile() {
        $admin_id=$this->is_login();
        $this->output->set_content_type('application/json');
        $this->form_validation->set_rules('name', 'First Name', 'required');
        $this->form_validation->set_rules('support_email', 'Support E-mail', 'required');
        $this->form_validation->set_rules('address', 'Address', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        
        if (!empty($_FILES['image_url']['name'])) {
            $path = "uploads/profilePic";
            $file_name ="image_url";
            $image_url = $this->doUploadImage($path,$file_name);
            if ($this->upload->display_errors()) {
                $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Admin Company Logo :'.strip_tags($this->upload->display_errors())]));
                $this->session->unset_userdata('error');
                return FALSE;
            }
        } else {
            $admin = $this->admin_model->getAdminDetail($admin_id);
            $image_url = $admin['image_url'];
        }
        if (!empty($_FILES['profile_image']['name'])) {
            $path = "uploads/profilePic";
            $file_name ="profile_image";
            $profile_image = $this->doUploadImage($path,$file_name);
            if($this->upload->display_errors()) {
                $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Admin Profile Image :'.strip_tags($this->upload->display_errors())]));
                $this->session->unset_userdata('error');
                return FALSE;
            }
        } else {
            $admin = $this->admin_model->getAdminDetail($admin_id);
            $profile_image = $admin['profile_image'];
        }
        if (!empty($_FILES['favicon_icon']['name'])) {
            $path = "uploads/profilePic";
            $file_name ="favicon_icon";
            $favicon_icon = $this->doUploadImage($path,$file_name);
            if($this->upload->display_errors()) {
                $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Favicon Icon :'.strip_tags($this->upload->display_errors())]));
                $this->session->unset_userdata('error');
                return FALSE;
            }
        } else {
            $admin = $this->admin_model->getAdminDetail($admin_id);
            $favicon_icon = $admin['favicon_icon'];
        }
        $result=$this->admin_model->updateProfile($admin_id,$image_url,$profile_image,$favicon_icon);
        
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'msg' => 'Profile Updated Succesfully','url' => base_url('admin/profile')]));
            return FALSE;
        } else {
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Something Went Wrong']));
            return FALSE;
        }
    }

    public function changePassword(){
        $admin_id=$this->is_login();
        $this->output->set_content_type('application/json');
        $this->form_validation->set_rules('old_password', 'Old Password', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->do_check_oldpassword($admin_id);
        if (!empty($result)) {
            $this->form_validation->set_rules('new_password', 'New Password', 'required');
            $this->form_validation->set_rules('confirm_new_password', 'Confirm Password', 'required');
            if ($this->form_validation->run() === FALSE) {
                $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
                return FALSE;
            }

            if($this->input->post('new_password')==$this->input->post('confirm_new_password')){
                $changed = $this->admin_model->do_reset_passowrd($admin_id);
                if ($changed) {
                    $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin'), 'msg' => 'Password successfully changed.']));
                    return FALSE;
                }else{
                    $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Old Password and New Password should not be same.']));
                    return FALSE;
                }
            }else{
                $this->output->set_output(json_encode(['result' => -1, 'msg' => 'New password and Confirm Password should be same.']));
                return FALSE;
            }
        } else {
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Old password did not matched current password.']));
            return FALSE;
        }
    }

    public function social(){
        $admin_id=$this->is_login();
        $data['title']='Social';
        $data['social_link']=$this->config->item('social_link');
        $data['social_data']=$this->admin_model->get_social_link();
        $this->dashboard_loadview('social',$data);
    }

    public function add_social_link(){
        $admin_id=$this->is_login();
        $this->output->set_content_type('application/json');
        $result=$this->admin_model->add_social_link();
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin/social'), 'msg' => 'Social link updated successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => 0, 'msg' => 'OOPs Something went wrong']));
            return FALSE;
        }
    }

    public function site_setting($key){
        $admin_id = $this->is_login();
        // echo $key; die;
        if($key == 'about'){
            $data['title'] = 'About Us';
        }
        $data['basic_datatable'] = '1';
        $data['type'] = $key;
        $data['site_setting'] = $this->admin_model->site_setting($key);
        $this->dashboard_loadview('site_setting',$data);
    }

    public function update_site_setting(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('description', 'Description', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result=$this->admin_model->update_site_setting();
        $key = $this->input->post('type');
        if($key == 'about'){
            $url = base_url('admin/about-us');
            $title = 'About Us';
        }
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => $url, 'msg' => $title.' Updated successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'No changes found.']));
            return FALSE;
        }
    }

    function change_status($id,$status,$table,$unique_id,$status_variable){
        $this->output->set_content_type('application/json');
        change_status($id,$status,$table,$unique_id,$status_variable);
        if($status == 'Deleted'){
            $msg = ucwords(str_replace('_', ' ', $table)).' deleted successfully.';
            if($table == 'categories'){
                $this->admin_model->deleteProductsByCategory($id);
            }
        }else{
            $msg = ucwords(str_replace('_', ' ', $table)).' status change to '.strtolower($status).' successfully.';
        }
        $this->output->set_output(json_encode(['result' => 1,'msg'=> $msg]));
        return FALSE;
    }

    public function users($gender=NULL){
        $admin_id = $this->is_login();
        $data['title'] = 'Users';
        $data['basic_datatable'] = '1';
        $data['users'] = $this->admin_model->getAllUsers();
        $this->dashboard_loadview('users/users',$data);
    }


    public function notification(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $data['user_id'] = $this->input->post('user_id');
        $data['data'] = NULL;
        $model_wrapper = $this->load->view('admin/users/notification',$data,true);
        $this->output->set_output(json_encode(['result' => 1, 'model_wrapper' => $model_wrapper]));
        return false;
    }

    public function send_notification(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('subject', 'Subject', 'required');
        $this->form_validation->set_rules('message', 'Message', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        
        $user_id = $this->input->post('user_id');
        $message = $this->input->post('message');
        $subject = $this->input->post('subject');
        foreach($user_id as $id){
            $user_detail = $this->admin_model->getUserDetail($id);
            $this->send_notification_mail($message,$user_detail['name'],$user_detail['email'],$subject);
            $notification = $this->admin_model->save_notification($id);
        }
        if($notification){
            $this->output->set_output(json_encode(['result' => 1, 'msg' => 'Notification Sent Successfully.','url'=> base_url('admin/users')]));
            return false;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'Something went wrong.']));
            return false;
        }
    }

    public function send_notification_mail($message, $name, $email,$subject){
        $htmlContent = "<h3>Dear " . $name . ",</h3>";
        $htmlContent .= "<div style='padding-top:8px;'>".$message."</div>";
        $admin_id=$this->session->userdata('admin_id');
        $admin_detail=$this->admin_model->getAdminSupportEmail($admin_id);
        if(!empty($admin_detail)){
            $from = $admin_detail['support_email'];
        }else{
            $from = 'support@hoof_boot.com';
        }
        $to = $email;
        $subject = "[Hoof Boot] ".$subject;
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        @mail($to, $subject, $htmlContent, $headers);
        return true;
    }

    public function users_detail($id){
        $admin_id = $this->is_login();
        $data['title'] = 'User Notification';
        $data['basic_datatable']='1';
        $data['user_detail'] = $this->admin_model->getUserDetail($id);
        $data['user_notification'] = $this->admin_model->getUserNotification($id);
        $this->dashboard_loadview('users/user-detail',$data);
    }
    
    public function contact(){
        $admin_id = $this->is_login();
        $data['title'] = 'Contact us';
        $data['basic_datatable'] = '1';
        $data['contact'] = $this->admin_model->getAllcontact();
        $this->dashboard_loadview('contact/contact_list',$data);
    }

    public function open_edit_contact_form($id=null){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $data['title']= "Contact Form"; 
        if(!empty($id)){
            $data['contact_detail'] = $this->admin_model->get_contant_by_id($id);
        }
        $model_wrapper = $this->load->view('admin/contact/contact-form',$data,true);
        $this->output->set_output(json_encode(['result' => 1, 'model_wrapper' => $model_wrapper]));
        return false;
    }

    public function add_contact(){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('country_code', 'Country Code', 'required');
        $this->form_validation->set_rules('phone', 'Phone', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->add_contact();
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin/contact'), 'msg' => 'Contact Added successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'OOPs Something went wrong']));
            return FALSE;
        }
    }

    public function update_contact($id){
        $this->output->set_content_type('application/json');
        $admin_id=$this->is_login();
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('country_code', 'Country Code', 'required');
        $this->form_validation->set_rules('phone', 'Phone', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->output->set_output(json_encode(['result' => 0, 'errors' => $this->form_validation->error_array()]));
            return FALSE;
        }
        $result = $this->admin_model->update_contact($id);
        if ($result) {
            $this->output->set_output(json_encode(['result' => 1, 'url' => base_url('admin/contact'), 'msg' => 'Contact Updated successfully.']));
            return FALSE;
        }else{
            $this->output->set_output(json_encode(['result' => -1, 'msg' => 'OOPs Something went wrong']));
            return FALSE;
        }
    }
    
}
?>