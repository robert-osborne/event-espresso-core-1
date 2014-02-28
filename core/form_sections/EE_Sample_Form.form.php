<?php
class EE_Sample_Form extends EE_Form_Section_Proper{
	function __construct(){
		$this->_subsections = array(
			'name'=>new EE_Text_Input(array('required'=>true,'default'=>'your name here')),
			'email'=>new EE_Email_Input(array('required'=>false)),
			'shirt_size'=>new EE_Select_Input(array(''=>'Please select...', 's'=>  __("Small", "event_espresso"),'m'=>  __("Medium", "event_espresso"),'l'=>  __("Large", "event_espresso")),'string',array('required'=>true)),
			'month_normal'=>new EE_Month_Input(),
			'month_leading_zero'=>new EE_Month_Input(true),
			'year_2'=>new EE_Year_Input(false, 1, 1),
			'year_4'=>new EE_Year_Input(true, 0,10,array('default'=>'2017')),
			'yes_no'=>new EE_Yes_No_Input(array('html_label_text'=>  __("Yes or No", "event_espresso"))),
			'credit_card'=>new EE_Credit_Card_Input(),
			'image_1'=>new EE_Admin_File_Uploader_Input(),
			'image_2'=>new EE_Admin_File_Uploader_Input()
		);
		$this->_layout_strategy = new EE_Div_Per_Section_Layout();
		parent::__construct();
	}
	
	/**
	 * Extra validation for the 'name' input.
	 * @param EE_Text_Input $form_input
	 */
	function _validate_name($form_input){
		if($form_input->sanitized_value() != 'Mike'){
			$form_input->add_validation_error(__("You are not mike. You must be brent or darren. Thats ok, I guess", 'event_espresso'), 'not-mike');
		}
	}
	
	function _validate(){
		parent::_validate();
		if($this->_subsections['shirt_size']->sanitized_value() =='s'
				&& $this->_subsections['year_4']->sanitized_value() < 2010){
			$this->add_validation_error(__("If you want a small shirt, you should be born after 2010. Otherwise theyre just too big", 'event_espresso'), 'too-old');
		}
	}
}