<?php
/**
 * Contains test class for /core/helpers/EEH_Parse_Shortcodes.helper.php
 *
 * @since  		4.6
 * @package 		Event Espresso
 * @subpackage 	tests
 */


/**
 * All tests for the EEH_Parse_Shortcodes class.
 * The tests here are more integration type tests than pure unit tests due to the nature of the
 * messages system.
 *
 * @since 		4.6
 * @package 		Event Espresso
 * @subpackage 	tests
 * @group messages
 * @group agg
 */
class EEH_Parse_Shortcodes_Test extends EE_UnitTestCase {


	/**
	 * This will hold the created event object on setup, which can then be used to grab expected
	 * data from.
	 *
	 * @var EE_Event
	 */
	protected $_event = null;



	/**
	 * This will hold the created datetime object on setup which can then be used to grab
	 * expected data from.
	 *
	 * @var EE_Datetime
	 */
	protected $_datetime = null;



	/**
	 * This will hold the created ticket object on setup which can then be used to grab expected
	 * data from.
	 *
	 * @var null
	 */
	protected $_ticket = null;



	public function setUp() {
		parent::setUp();

		//all shortcode parse tests will require a full event to be setup with some datetimes and tickets.
		$price = $this->factory->price_chained->create_object( array('PRC_name' => 'Not Free Price', 'PRC_amount' => '125.00' ) );
		$this->_ticket = $this->factory->ticket_chained->create_object( array( 'PRC_ID' => $price->ID() ) );
		//update ticket price
		$this->_ticket->set( 'TKT_price', '125.00' );
		$this->_ticket->set( 'TKT_name', 'Podracing Entry' );
		$this->_ticket->set( 'TKT_description', 'One entry in the event.' );
		$this->_datetime = $this->_ticket->first_datetime();
		$this->_event = $this->_datetime->event();
	}




	/**
	 * This grabs an EE_Messages_Addressee object for the Preview data handler.
	 *
	 * @return EE_Messages_Addressee
	 */
	protected function _get_addressee( $context = 'primary_attendee' ) {
		$data = new EE_Messages_Preview_incoming_data( array( 'event_ids' => array( $this->_event->ID() ) ) );

		/**
		 * @see EE_message_type::_init_data()
		 */
		$addressee_data = array(
			'billing' => $data->billing,
			'taxes' => $data->taxes,
			'tax_line_items' => $data->tax_line_items,
			'additional_line_items' => $data->additional_line_items,
			'grand_total_line_item' => $data->grand_total_line_item,
			'txn' => $data->txn,
			'payments' => $data->payments,
			'payment' => isset($data->payment) ? $data->payment : NULL,
			'reg_objs' => $data->reg_objs,
			'registrations' => $data->registrations,
			'datetimes' => $data->datetimes,
			'tickets' => $data->tickets,
			'line_items_with_children' => $data->line_items_with_children,
			'questions' => $data->questions,
			'answers' => $data->answers,
			'txn_status' => $data->txn_status,
			'total_ticket_count' => $data->total_ticket_count
			);

		if ( is_array( $data->primary_attendee_data ) ) {
			$addressee_data = array_merge( $addressee_data, $data->primary_attendee_data );
			$addressee_data['primary_att_obj'] = $data->primary_attendee_data['att_obj'];
			$addressee_data['primary_reg_obj'] = $data->primary_attendee_data['reg_obj'];
		}

		/**
		 * @see EE_message_type::_process_data()
		 */
		switch ( $context ) {
			case 'primary_attendee'  :
				$aee = $addressee_data;
				$aee['events'] = $data->events;
				$aee['attendees'] = $data->attendees;
				return new EE_Messages_Addressee( $aee );
				break;
			case 'attendee' :
				//for the purpose of testing we're just going to do ONE attendee
				$attendee = reset( $data->attendees );
				foreach ( $attendee as $item => $value ) {
					$aee[$item] = $value;
					if ( $item == 'line_ref' ) {
						foreach( $value as $event_id ) {
							$aee['events'][$event_id] = $data->events[$event_id];
						}
					}
				}
				$aee['reg_obj'] = array_shift( $attendee['reg_objs'] );
				$aee['attendees'] = $data->attendees;
				return new EE_Messages_Addressee( $aee );
				break;
			case 'admin' :
				//for the purpose of testing we're only setting up for the event we have active for testing.
				$aee['user_id'] = $this->_event->get( 'EVT_wp_user' );
				$aee['events'] = $data->events;
				$aee['attendees'] = $data->attendees;
				return new EE_Messages_Addressee( $aee );
		}

	}




	/**
	 * This helper returns parsed content from the parser to be used for tests using the given params.
	 *
	 * @param string $messenger      The slug for the messenger being tested.
	 * @param string $message_type The slug for the message type being tested.
	 * @param string $field                  The field being tested.
	 * @param string $context             The context being tested.
	 *
	 * @return string The parsed content.
	 */
	protected function _get_parsed_content( $messenger, $message_type, $field, $context ) {
		//grab the correct template  @see EE_message_type::_get_templates()
		$mtpg = EEM_Message_Template_Group::instance()->get_one( array( array(
			'MTP_messenger' => 'email',
			'MTP_message_type' => 'payment',
			'MTP_is_global' => true
			)));
		$all_templates = $mtpg->context_templates();

		foreach ( $all_templates as $context => $template_fields ) {
			foreach( $template_fields as $template_field=> $template_obj ) {
				$templates[$template_field][$context] = $template_obj->get('MTP_content');
			}
		}

		//instantiate messenger and message type objects
		$msg_class = 'EE_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $messenger ) ) ) . '_messenger';
		$mt_class = 'EE_' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $message_type ) ) ) . '_message_type';
		$messenger = new $msg_class();
		$message_type = new $mt_class();

		$message_type->set_messages( array(), $messenger, $context, true );

		//grab valid shortcodes and setup for parser.
		$m_shortcodes = $messenger->get_valid_shortcodes();
		$mt_shortcodes = $message_type->get_valid_shortcodes();

		//just sending in the content field and primary_attendee context/data for testing.
		$template = $templates[$field][$context];
		$valid_shortcodes = isset( $m_shortcodes[$field] ) ? $m_shortcodes[$field] : $mt_shortcodes[$context];
		$data = $this->_get_addressee();

		EE_Registry::instance()->load_helper('Parse_Shortcodes');
		$parser = new EEH_Parse_Shortcodes();
		return $parser->parse_message_template( $template, $data, $valid_shortcodes, $message_type, $messenger, $context, $mtpg->ID() );
	}



	/**
	 * Tests parsing the message template for email messenger, payment received message
	 * type.
	 *
	 * @group 7585
	 * @since 4.6
	 */
	public function test_parsing_email_payment_received() {
		$parsed = $this->_get_parsed_content( 'email', 'payment', 'content', 'primary_attendee' );

		//now that we have parsed let's test the results, note for the purpose of this test we are verifying transaction shortcodes and ticket shortcodes.

		//testing [PRIMARY_REGISTRANT_FNAME], [PRIMARY_REGISTRANT_LNAME]
		$this->assertContains( 'Luke Skywalker', $parsed );

		//testing [PAYMENT_STATUS]
		$this->assertContains( 'Incomplete', $parsed );

		//testing [TXN_ID]
		$this->assertContains( '999999', $parsed );

		//testing [TOTAL_COST] and [AMOUNT_DUE]  (should be $125*3 + 20 shipping charge + taxes)
		EE_Registry::instance()->load_helper( 'Template' );
		$total_cost = EEH_Template::format_currency( '398.00' );
		$this->assertContains( $total_cost, $parsed );
		//but we should also have a count of TWO for this string
		$this->assertEquals( 2, substr_count( $parsed, $total_cost ) );

		//testing [AMOUNT_PAID]
		$amount_paid = EEH_Template::format_currency( '0' );
		$this->assertContains( $amount_paid, $parsed );


		//testing [TICKET_NAME]
		$this->assertContains( 'Podracing Entry', $parsed );

		//testing [TICKET_DESCRIPTION]
		$this->assertContains( 'One entry in the event.', $parsed );

		//testing [TICKET_PRICE]
		$this->assertContains( EEH_Template::format_currency( '125.00' ), $parsed );


		//testing [TKT_QTY_PURCHASED]
		$expected = '<strong>Quantity Purchased:</strong> 3';
		$this->assertContains( $expected, $parsed, '[TKT_QTY_PURCHASED] shortcode was not parsed correctly to the expected value which is 3' );

	}




	public function test_parsing_html_receipt() {

	}



} //end class EEH_Parse_Shortcodes_Test
