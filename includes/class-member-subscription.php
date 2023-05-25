<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

Class PMS_Member_Subscription {

	public $id = 0;

	public $user_id = 0;

	public $subscription_plan_id = 0;

	public $start_date;

	public $expiration_date;

	public $status;

	public $payment_profile_id;

	public $payment_gateway;

	public $billing_amount;

	public $billing_duration;

	public $billing_duration_unit;

	public $billing_cycles;

	public $billing_next_payment;

	public $billing_last_payment;

	public $trial_end;

	/**
	 * Construct
	 *
	 * @param array $data - the subscription data
	 *
	 */
	public function __construct( $data = array() ) {

		$this->set_instance( $data );

	}


	/**
	 * Sets the values of the object properties to the provided data
	 *
	 * @param array $data - the subscription data
	 *
	 */
	public function set_instance( $data = array() ) {

		// Grab all properties and populate them
        foreach( get_object_vars( $this ) as $property => $value ) {

            if( isset( $data[$property] ) ) {

            	// Empty dates overwrite
            	if( $data[$property] == '0000-00-00 00:00:00' )
            		$data[$property] = '';

                $this->$property = $data[$property];

            }

        }

	}


	/**
	 * Clears the instance data
	 *
	 */
	public function clear_instance() {

		foreach( get_class_vars( __CLASS__ ) as $property => $value ) {
			$this->$property = $value;
		}


	}
	public function checkUserScreens($user , $subId) {
		global $wpdb;
		$db_name = $wpdb->dbname;
		$db_user = $wpdb->dbuser;
		$db_password = $wpdb->dbpassword;
		$db_host = $wpdb->dbhost;

		try {
			$pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die("Database connection failed: " . $e->getMessage());
		}

		$query = "SELECT * FROM screens where user_id=:user_id and subscriptionId=:subId";
		$stmt = $pdo->prepare($query);
		$stmt->bindParam(':user_id', $user->ID, PDO::PARAM_INT);
		$stmt->bindParam(':subId', $subId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->rowCount();
	}

	public function getPlan($plan_name) {
		global $wpdb;
		$db_name = $wpdb->dbname;
		$db_user = $wpdb->dbuser;
		$db_password = $wpdb->dbpassword;
		$db_host = $wpdb->dbhost;

		try {
			$pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die("Database connection failed: " . $e->getMessage());
		}

		$query = "SELECT * FROM ahq_plans where plan_name = :plan_name";
		$stmt = $pdo->prepare($query);
		$stmt->bindParam(':plan_name', $plan_name, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_OBJ) : null; 
	}

	/**
	 * Inserts a new member subscription into the database
	 *
	 * @param array $data - the array of data for the member subscription
	 *
	 * @return mixed int|false
	 *
	 */
	public function insert( $data = array() ) {

		global $wpdb;
		$db_name = $wpdb->dbname;
		$db_user = $wpdb->dbuser;
		$db_password = $wpdb->dbpassword;
		$db_host = $wpdb->dbhost;

		try {
			$pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die("Database connection failed: " . $e->getMessage());
		}

		// Clean the data array
        $data = $this->sanitize_data( $data );

		$planId = $data['subscription_plan_id'];
		$plan = pms_get_subscription_plan($planId);
		$user_id = $data['user_id'];
		$current_user = wp_get_current_user();
		$screens= $this->getPlan($plan->name)->screens;

		
		if ( $current_user instanceof WP_User ) {
			if($this->checkUserScreens($current_user , $planId) == 0) {
				$query = "INSERT INTO screens (user_id , username , screens , subscriptionId) VALUES (:user_id , :username , :screens , :subId)";
				$stmt = $pdo->prepare($query);
				$stmt->bindParam(':user_id', $current_user->ID, PDO::PARAM_INT);
				$stmt->bindParam(':username', $current_user->user_login, PDO::PARAM_STR);
				$stmt->bindParam(':subId', $planId, PDO::PARAM_INT);
				$stmt->bindParam(':screens', $screens, PDO::PARAM_INT);
				$stmt->execute();
			}
		}


        // Insert member subscription
		$insert_result = $wpdb->insert( $wpdb->prefix . 'pms_member_subscriptions', $data );

		if( $insert_result ) {

            // Populate current object
            $this->id = $wpdb->insert_id;
            $this->set_instance( $data );

            /**
             * Fires right after the Member Subscription db entry was inserted into the db
             *
             * @param int   $id               - the id of the new member subscription
             * @param array $data             - the provided data for the current member subscription
             *
             */
            do_action( 'pms_member_subscription_insert', $this->id, $data );

            return $this->id;

        }

        return false;

	}


	/**
	 * Updates an existing member subscription with the new provided data
	 *
	 * @param array $data - the new datas to be updated for the member subscription
	 *
	 * @return bool
	 *
	 */
	public function update( $data = array() ) {

		global $wpdb;

		

		// Clean the data array
		$data = $this->sanitize_data( $data );

		// We don't want the id to be updated
		if( isset( $data['id'] ) )
			unset( $data['id'] );



		// Update the member subscription
		$update_result = $wpdb->update( $wpdb->prefix . 'pms_member_subscriptions', $data, array( 'id' => $this->id ) );

		// Can return 0 if no rows are affected
        if( $update_result !== false )
            $update_result = true;


		if( $update_result ) {

			global $wpdb;
			$db_name = $wpdb->dbname;
			$db_user = $wpdb->dbuser;
			$db_password = $wpdb->dbpassword;
			$db_host = $wpdb->dbhost;
			$plan = pms_get_subscription_plan($data['subscription_plan_id']);
			$ahqPlan = $this->getPlan($plan->name);
			try {
				$pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
				die("Database connection failed: " . $e->getMessage());
			}

			$query = "UPDATE screens SET subscriptionId = :subId , screens = :screens WHERE subscriptionId=:osbid and user_id=:user_id";
			$stmt = $pdo->prepare($query);
			$stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
			$stmt->bindParam(':subId', $data['subscription_plan_id'], PDO::PARAM_INT);
			$stmt->bindParam(':osbid', $this->subscription_plan_id, PDO::PARAM_INT);
			$stmt->bindParam(':screens',$ahqPlan->screens , PDO::PARAM_INT);

			$stmt->execute();


			/**
			 * Fires right after the Member Subscription db entry was updated
			 *
			 * @param int 	$id 		   - the id of the subscription that has been updated
			 * @param array $data 		   - the array of values to be updated for the subscription
			 * @param array $old_data 	   - the array of values representing the subscription before the update
			 *
			 */
			do_action( 'pms_member_subscription_update', $this->id, $data, $this->to_array() );

			// Update the current instance with the new data values
			$this->set_instance( $data );

		}

		return $update_result;

	}


	/**
	 * Removes the current member subscription from the database
	 *
	 * @return bool
	 *
	 */
	public function remove() {

		global $wpdb;

		$delete_result = $wpdb->delete( $wpdb->prefix . 'pms_member_subscriptions', array( 'id' => $this->id ) );
		$userId = $this->user_id;
		$sid = $this->id;

		// Can return 0 if no rows are affected
        if( $delete_result !== false )
            $delete_result = true;

        if( $delete_result ) {
			
			global $wpdb;
			$db_name = $wpdb->dbname;
			$db_user = $wpdb->dbuser;
			$db_password = $wpdb->dbpassword;
			$db_host = $wpdb->dbhost;

			try {
				$pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
				die("Database connection failed: " . $e->getMessage());
			}

			$query = "DELETE FROM screens WHERE user_id = :user_id and subscriptionId =:subId";
			$stmt = $pdo->prepare($query);
			$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindParam(':subId', $sid, PDO::PARAM_INT);
			$stmt->execute();


			/**
			 * Fires right after a member subscription has been deleted, but before metadata is deleted
			 *
			 * @param int   $id   	  - the id of the member subscription that has just been deleted from the db
			 * @param array $old_data - the data the subscription had at the moment of deletion
			 *
			 */
			do_action( 'pms_member_subscription_before_metadata_delete', $this->id, $this->to_array() );

        	/**
        	 * Remove all meta data
        	 *
        	 */
        	$meta_delete_result = $wpdb->delete( $wpdb->prefix . 'pms_member_subscriptionmeta', array( 'member_subscription_id' => $this->id ) );

        	/**
	         * Fires right after a member subscription has been deleted
	         *
	         * @param int   $id   	  - the id of the member subscription that has just been deleted from the db
	         * @param array $old_data - the data the subscription had at the moment of deletion
	         *
	         */
	        do_action( 'pms_member_subscription_delete', $this->id, $this->to_array() );

	        // Clear the current object instance
	        $this->clear_instance();

        }

        return $delete_result;

	}


	/**
	 * Verifies if the current subscription is auto renewing
	 * What this means is that it either has a subscription equivalent in one of the payment gateways
	 * or that it has a renewal schedule set in the database for it
	 *
	 * @return bool
	 *
	 */
	public function is_auto_renewing() {

		if( $this->status == 'expired' || $this->status == 'canceled' )
			return false;

        // One time payments with trial for PayPal Standard and Express have a payment_profile_id, but they are not auto renewing
        $subscription_payment_type = pms_get_member_subscription_meta( $this->id, 'pms_payment_type', true );
        if( $subscription_payment_type == 'one_time_payment' )
            return false;

		if( ! empty( $this->payment_profile_id ) )
			return true;

		if( ( ! empty( $this->billing_duration ) && ! empty( $this->billing_duration_unit ) ) )
			return true;

		return false;

	}


    /**
     * Checks to see if the current subscription is in its trial period or not
     *
     * @return bool
     *
     */
    public function is_trial_period() {

        if( empty( $this->trial_end ) )
            return false;

        if( strtotime( $this->trial_end ) < time() )
            return false;

        return true;

    }


	/**
	 * Eliminate all values from the provided data array that are not a part of the object
	 *
	 * @param array $data
	 *
	 * @return array
	 *
	 */
	private function sanitize_data( $data = array() ) {

		// Strip data of any script tags
		$data = pms_array_strip_script_tags( $data );

		$object_vars = array_keys( get_object_vars( $this ) );

        foreach( $data as $key => $val ) {

            if( !in_array( $key, $object_vars ) )
                unset( $data[$key] );

        }

        return $data;

	}


	/**
	 * Returns the array representation of the current object instance
	 *
	 */
	public function to_array() {

		return get_object_vars( $this );

	}

}
