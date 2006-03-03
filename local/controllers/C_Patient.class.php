<?php
require_once CELINI_ROOT."/ordo/ORDataObject.class.php";
require_once CELINI_ROOT."/includes/Grid.class.php";

/**
 * Controller Clearhealth Patient actions
 */
class C_Patient extends Controller {

	var $number_id = 0;
	var $address_id = 0;
	var $identifier_id = 0;
	var $insured_relationship_id = 0;
	var $person_person_id = 0;
	var $patient_statistics_id = 0;


	/**
	 * Edit/Add an Patient
	 *
	 */
	function actionEdit($patient_id = 0) {
		if (isset($this->patient_id)) {
			$patient_id = $this->patient_id;
		}

		$GLOBALS['C_MAIN']['noOverlib'] = true;

		$ajax =& Celini::ajaxInstance();
		$ajax->jsLibraries[] = array('clnipopup');

		$user =& ORdataObject::factory('User');
		$person =& ORdataObject::factory('Patient',$patient_id);
		$number =& ORDataObject::factory('PersonNumber',$this->number_id,$patient_id);
		$address =& ORDataObject::factory('PersonAddress',$this->address_id,$patient_id);
		$identifier =& ORDataObject::factory('Identifier',$this->identifier_id,$patient_id);
		
		if ($person->isPopulated()) {
			$this->set('patient_id',$person->get('id'));
			$this->set('external_id',$person->get('id'));
		}


		$nameHistoryGrid =& new cGrid($person->loadDatasource('NameHistoryList'));
		$nameHistoryGrid->name = "nameHistoryGrid";
		$identifierGrid =& new cGrid($person->identifierList());
		$identifierGrid->name = "identifierGrid";
		$identifierGrid->registerTemplate('identifier','<a href="'.Celini::ManagerLink('editIdentifier',$patient_id).'id={$identifier_id}&process=true">{$identifier}</a>');
		$identifierGrid->registerTemplate('actions','<a href="'.Celini::ManagerLink('deleteIdentifier',$patient_id).'id={$identifier_id}&process=true">delete</a>');
		$identifierGrid->setLabel('actions',false);

		$insuredRelationshipGrid =& new cGrid($person->loadDatasource('InsuredRelationshipList'));
		$insuredRelationshipGrid->name = "insuredRelationshipGrid";
		$insuredRelationshipGrid->registerTemplate('company','<a href="'.Celini::ManagerLink('editInsuredRelationship',$patient_id).'id={$insured_relationship_id}&process=true">{$company}</a>');
		$insuredRelationshipGrid->indexCol = false;

		$insuredRelationship =& ORDataObject::factory('InsuredRelationship',$this->insured_relationship_id,$patient_id);
		$this->payerCount = $insuredRelationship->numRelationships($patient_id);

		$subscriber =& ORDataObject::factory('Patient',$insuredRelationship->get('subscriber_id'));

		$insuranceProgram =& ORDataObject::Factory('InsuranceProgram');
		$this->assign_by_ref('insuranceProgram',$insuranceProgram);

		$personPerson =& ORDataObject::factory('PersonPerson',$this->person_person_id,$patient_id);
		$personPersonGrid = new cGrid($person->loadDatasource('RelatedList'));
		$personPersonGrid->name = "personPersonGrid";
		$personPersonGrid->registerTemplate('relation_type','<a href="'.Celini::ManagerLink('editPersonPerson',$patient_id).'id={$person_person_id}&process=true">{$relation_type}</a>');

		$building =& ORDataOBject::factory('Building');
		$encounter =& ORDataOBject::factory('Encounter');
		
		$patientStatistics =& ORDataObject::factory('PatientStatistics',$patient_id);

		$pcc =& Celini::newOrdo('PatientChronicCode');
		$chronicCodes = $pcc->patientCodeArray($patient_id,true);
		
		$this->assign("providers_array",$this->utility_array($user->users_factory("provider"),"id","username"));
		$this->assign_by_ref('person',$person);
		$this->assign_by_ref('building',$building);
		$this->assign_by_ref('encounter',$encounter);
		$this->assign_by_ref('number',$number);
		$this->assign_by_ref('address',$address);
		$this->assign_by_ref('identifier',$identifier);
		$this->assign_by_ref('nameHistoryGrid',$nameHistoryGrid);
		$this->assign_by_ref('identifierGrid',$identifierGrid);
		$this->assign_by_ref('insuredRelationship',$insuredRelationship);
		$this->assign_by_ref('insuredRelationshipGrid',$insuredRelationshipGrid);
		$this->assign_by_ref('personPerson',$personPerson);
		$this->assign_by_ref('personPersonGrid',$personPersonGrid);
		$this->assign_by_ref('patientStatistics',$patientStatistics);
		$this->assign_by_ref('subscriber',$subscriber);
		$this->assign('FORM_ACTION',Celini::managerLink('update',$patient_id));
		$this->assign('EDIT_NUMBER_ACTION',Celini::managerLink('editNumber',$patient_id));
		$this->assign('DELETE_NUMBER_ACTION',Celini::managerLink('deleteNumber',$patient_id));
		$this->assign('EDIT_ADDRESS_ACTION',Celini::managerLink('editAddress',$patient_id));
		$this->assign('DELETE_ADDRESS_ACTION',Celini::managerLink('deleteAddress',$patient_id));
		$this->assign('NEW_PAYER',Celini::managerLink('editInsuredRelationship',$patient_id)."id=0&&process=true");
		$this->assign('hide_type',true);
		$this->assign('chronicCodes',$chronicCodes);

		$this->assign('now',date('Y-m-d'));

		if ($this->GET->exists('view') && $this->GET->get('view') === 'narrow') {
			return $this->view->render("singleColEdit.html");
		}
		return $this->view->render("edit.html");
	}

	/**
	 * List Patients
	 */
	function actionList_view() {
		$person =& ORDataObject::factory('Patient');

		$ds =& $person->patientList();
		$ds->template['name'] = "<a href='".Celini::link('view','PatientDashboard')."id={\$person_id}'>{\$name}</a>";
		$grid =& new cGrid($ds);
		$grid->pageSize = 50;

		$this->assign_by_ref('grid',$grid);

		return $this->view->render("list.html");
	}

	function actionFamilyStatement_view($patientId = false) {
		return $this->actionStatement_view($patientId,true);
	}

	function _ordoSnap($name,&$ordo) {
		if (isset($this->data['ordo'][$name])) {
			$ordo->populateArray($this->data['ordo'][$name]);
		}
		else {
			$this->data['ordo'][$name] = $ordo->helper->persistToArray($ordo);
		}
	}

	/**
	 * @todo figure out somewhere else to put all this sql, im not sure if a ds works with the derived tables, but maybe it does
	 */
	function actionStatement_view($patientId = false,$includeDependants=false) {
		if (!$patientId) {
			$patientId = $this->get('patient_id');
		}
		EnforceType::int($patientId);


		$reportId = $this->GET->get('report_id');
		if (!$reportId) {
			$reportId = $_GET[0];
		}
		$r =& Celini::newOrdo('Report',$reportId);
		$fromSnapshot = false;
		if ($this->GET->get('snapshot') == 'true' || $r->get('snapshot_style') == 1) {
			$rs =& Celini::newOrdo('ReportSnapshot');
			$rs->persist();
			$this->view->rs =& $rs;
			$this->data = array();
			$this->data['ordo'] = array();
		}
		else if ($this->GET->exists('fromSnapshot')) {
			$rs =& Celini::newOrdo('reportSnapshot',$this->GET->get('fromSnapshot'));
			$this->data = unserialize($rs->get('data'));
			$fromSnapshot = true;
		}

		$p =& Celini::newOrdo('Patient',$patientId);
		$this->_ordoSnap('Patient',$p);
		$this->view->assign_by_ref('patient',$p);

		$g =& $p->get('guarantor');
		$this->_ordoSnap('Guarantor',$g);
		if ($g->isPopulated()) {
			$this->view->assign_by_ref('guarantor',$g);
			$this->view->assign_by_ref('guarantorAddress',$g->address());
		}
		else {
			$this->view->assign_by_ref('guarantor',$p);
			$this->view->assign_by_ref('guarantorAddress',$p->address());
		}
		$this->_ordoSnap('Guarantor',$g);

		
		$pro =& $p->get('defaultProviderPerson');
		$this->_ordoSnap('defaultProviderPerson',$pro);
		$this->view->assign_by_ref('provider',$pro);

		$practice =& $p->get('defaultPractice');
		$this->_ordoSnap('defaultPractice',$practice);
		$this->view->assign_by_ref('practice',$practice);

		$practiceAddress =& $practice->get('billingAddress');
		$this->_ordoSnap('practiceAddress',$practiceAddress);
		$this->view->assign_by_ref('practiceAddress',$practiceAddress);


		$sh =& Celini::newOrdo('StatementHistory');
		$this->_ordoSnap('StatementHistory',$sh);
		$sh->set('patient_id',$patientId);
		$sh->set('type',1); // 1 is for print 2 is for preview
		if (isset($rs)) {
			$sh->set('report_snapshot_id',$rs->get('id'));
		}

		if (!$fromSnapshot) {
			$sh->persist();
		}

		$this->assign('statement_date',$sh->get('date_generated'));
		$this->assign('statement_number',$sh->get('statement_number'));
		$this->assign('pay_by',$sh->get('pay_by'));


		$aging = array(
			0=>0,
			30=>0,
			60=>0,
			90=>0,
			120=>0,
		);


		$db = new clniDb();

		list($sql,$agingSql) = $this->_genPatientStatementSql($patientId,$includeDependants);	

		$res = $db->execute($sql);

		$lines = array();
		$total_charges = 0;
		$total_credits = 0;
		$total_outstanding = 0;
		while($res && !$res->EOF) {
			$total_charges += $res->fields['charge'];
			$total_credits += $res->fields['credit'];
			$res->fields['outstanding'] = number_format($total_charges - $total_credits,2);
			$lines[] = $res->fields;
			$res->MoveNext();
		}

		if ($fromSnapshot && isset($this->data['ordo']['lines'])) {
			$lines = $this->data['ordo']['lines'];
		}
		else {
			$this->data['ordo']['lines'] = $lines;
		}

		if ($fromSnapshot && isset($this->data['ordo']['balance'])) {
			$total_charges = $this->data['ordo']['balance']['total_charges'];
			$total_credits = $this->data['ordo']['balance']['total_credits'];
			$total_outstanding = $this->data['ordo']['balance']['total_outstanding'];
		}
		else {
			$this->data['ordo']['balance']['total_charges'] = $total_charges;
			$this->data['ordo']['balance']['total_credits'] = $total_credits;
			$this->data['ordo']['balance']['total_outstanding'] = $total_outstanding;
		}

		$this->assign('lines',$lines);

		$balance = $total_charges-$total_credits;

		$this->assign('total_charges',$total_charges);
		$this->assign('total_credits',$total_credits);
		$this->assign('total_outstanding',$balance);
		
		$this->assign('total_account_balance',number_format($balance,2));
		$this->assign('insurance_pending',number_format(0,2));
		$this->assign('current_balance_due',number_format($balance,2));

		$sh->set('amount',$balance);
		$sh->persist();

		$res = $db->execute($agingSql);
		while($res && !$res->EOF) {
			$aging[$res->fields['period']] += $res->fields['balance'];
			$res->MoveNext();
		}

		if ($fromSnapshot && isset($this->data['ordo']['aging'])) {
			$aging = $this->data['ordo']['aging'];
		}
		else {
			$this->data['ordo']['aging'] = $aging;
		}

		$this->assign('aging',$aging);

		if (isset($rs)) {
			$rs->set('data',serialize($this->data));
		}

		if (isset($this->noRender) && $this->noRender === true) {
			return "statement.html";
		}
		return $this->view->render("statement.html");
	}

	function _genPatientStatementSql($patientId,$includeDependants) {
		$format = DateObject::getFormat();

		$patientSelectSql = "
		e.patient_id = $patientId
		";
		$this->assign('familyStatement',false);
		if ($includeDependants) {
			$patientSelectSql = "(e.patient_id =$patientId or e.patient_id 
				in(select person_id from person_person where related_person_id = $patientId and guarantor = 1))";
			$this->assign('familyStatement',true);
		}

		$encounterBalanceSql = "
		select
			feeData.encounter_id,
			(charge - ifnull(credit,0.00)) balance
		from
			/* Fee total */
			(
			select
				e.encounter_id,
				sum(cd.fee) charge
			from
				encounter e
				inner join clearhealth_claim cc using(encounter_id)
				inner join coding_data cd on e.encounter_id = cd.foreign_id and cd.parent_id = 0
			where
				$patientSelectSql
			group by
				e.encounter_id
			) feeData
			left join
			/* Payment totals */
			(
			select
				e.encounter_id,
				(sum(pl.paid) + sum(pl.writeoff)) credit
			from
				encounter e
				inner join clearhealth_claim cc using(encounter_id)
				inner join payment p on cc.claim_id = p.foreign_id
				inner join payment_claimline pl on p.payment_id = pl.payment_id
			where
				$patientSelectSql
			group by
				e.encounter_id
			) paymentData on feeData.encounter_id = paymentData.encounter_id
		";

		$agingSql = "
		select
			e.encounter_id,
			CASE 
				WHEN (TO_DAYS(now()) - TO_DAYS(e.date_of_treatment)) < 30 THEN 0 
				WHEN (TO_DAYS(now()) - TO_DAYS(e.date_of_treatment)) < 60 THEN 30 
				WHEN (TO_DAYS(now()) - TO_DAYS(e.date_of_treatment)) < 90 THEN 60 
				WHEN (TO_DAYS(now()) - TO_DAYS(e.date_of_treatment)) < 120 THEN 90 
				ELSE 120
			END period,
			balance
		from
			encounter e
			inner join ($encounterBalanceSql) eb using(encounter_id)
		";


		// charges from claimlines
		$sql = "
		select * from (
		select
			date_format(e.date_of_treatment,'$format') item_date,
			c.code_text,
			c.code,
			cc.total_billed charge,
			0.00 credit,
			0.00 outstanding,
			e.encounter_id,
			concat_ws(', ',pr.last_name,pr.first_name) patient_name
		from
			encounter e
			inner join clearhealth_claim cc using(encounter_id)
			inner join coding_data cd on e.encounter_id = cd.foreign_id and cd.parent_id = 0
			inner join codes c using(code_id)
			inner join ($encounterBalanceSql) b on e.encounter_id = b.encounter_id
			inner join person pr on e.patient_id = pr.person_id
		where
			(e.status = 'billed' or e.status = 'closed') and
			$patientSelectSql and balance > 0
		";

		// payments from co-pays
		$sql .= "
		union
		select
			date_format(p.payment_date,'$format') item_date,
			'Co-Pay' code_text,
			'' code,
			0.00 charge,
			(pl.paid+pl.writeoff) credit,
			0.00 outstanding,
			e.encounter_id,
			concat_ws(', ',pr.last_name,pr.first_name) patient_name
		from
			encounter e
			inner join clearhealth_claim cc using(encounter_id)
			inner join payment p on e.encounter_id = p.encounter_id
			inner join payment_claimline pl using(payment_id)
			inner join ($encounterBalanceSql) b on e.encounter_id = b.encounter_id
			inner join person pr on e.patient_id = pr.person_id
		where
			(e.status = 'billed' or e.status = 'closed') and
			$patientSelectSql
			and balance > 0
		";

		// payments to claimlines
		$sql .= "
		union
		select
			date_format(p.payment_date,'$format') item_date,
			c.code_text,
			c.code,
			0 charge,
			(pl.paid+pl.writeoff) credit,
			0.00,
			e.encounter_id,
			concat_ws(', ',pr.last_name,pr.first_name) patient_name
		from
			encounter e
			inner join clearhealth_claim cc using(encounter_id)
			inner join payment p on cc.claim_id = p.foreign_id
			inner join payment_claimline pl using(payment_id)
			inner join codes c using(code_id)
			inner join ($encounterBalanceSql) b on e.encounter_id = b.encounter_id
			inner join person pr on e.patient_id = pr.person_id
		where
			(e.status = 'billed' or e.status = 'closed') and
			$patientSelectSql
			and p.encounter_id = 0
			and balance > 0
		) data
		order by encounter_id DESC , item_date
		";
		return array($sql,$agingSql);
	}
}
?>
