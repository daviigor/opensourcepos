<?php

use App\Models\Attribute;
use App\Models\Employee;
use App\Models\Item_taxes;
use App\Models\Tax_category;
use CodeIgniter\Database\ResultInterface;
use CodeIgniter\Session\Session;

/**
 * Tabular views helper
 */

/**
 * Basic tabular headers function
 */
function transform_headers_readonly(array $array): string	//TODO: $array needs to be refactored to a new name.  Perhaps $headers?
{
	$result = [];

	foreach($array as $key => $value)
	{
		$result[] = ['field' => $key, 'title' => $value, 'sortable' => $value != '', 'switchable' => !preg_match('(^$|&nbsp)', $value)];
	}

	return json_encode($result);
}

/**
 * Basic tabular headers function
 */
function transform_headers(array $array, bool $readonly = FALSE, bool $editable = TRUE): string	//TODO: $array needs to be refactored to a new name.  Perhaps $headers?
{
	$result = [];

	if(!$readonly)
	{
		$array = array_merge ([['checkbox' => 'select', 'sortable' => FALSE]], $array);
	}

	if($editable)
	{
		$array[] = ['edit' => ''];
	}

	foreach($array as $element)	//TODO: This might be clearer to refactor this to `foreach($headers as $header)`
	{
		reset($element);
		$result[] = [
			'field' => key($element),
			'title' => current($element),
			'switchable' => $element['switchable'] ?? !preg_match('(^$|&nbsp)', current($element)),
			'escape' => !preg_match("/(edit|phone_number|email|messages)/", key($element)) && !(isset($element['escape']) && !$element['escape']),
			'sortable' => $element['sortable'] ?? current($element) != '',
			'checkbox' => $element['checkbox'] ?? FALSE,
			'class' => isset($element['checkbox']) || preg_match('(^$|&nbsp)', current($element)) ? 'print_hide' : '',
			'sorter' => $element ['sorter'] ?? ''
		];
	}

	return json_encode($result);
}

/**
 * Get the header for the sales tabular view
 */
function get_sales_manage_table_headers(): string
{
	$headers = [
		['sale_id' => lang('Common.id')],
		['sale_time' => lang('Sales.sale_time')],
		['customer_name' => lang('Customers.customer')],
		['amount_due' => lang('Sales.amount_due')],
		['amount_tendered' => lang('Sales.amount_tendered')],
		['change_due' => lang('Sales.change_due')],
		['payment_type' => lang('Sales.payment_type')]
	];

	if(config('OSPOS')->settings['invoice_enable'])
	{
		$headers[] = ['invoice_number' => lang('Sales.invoice_number')];
		$headers[] = ['invoice' => '&nbsp', 'sortable' => FALSE, 'escape' => FALSE];
	}

	$headers[] = ['receipt' => '&nbsp', 'sortable' => FALSE, 'escape' => FALSE];

	return transform_headers($headers);
}

/**
 * Get the html data row for the sales
 */
function get_sale_data_row(object $sale): array
{
	$uri = current_url(true);
	$controller_name = $uri->getSegment(1);

	$row = [
		'sale_id' => $sale->sale_id,
		'sale_time' => to_datetime(strtotime($sale->sale_time)),
		'customer_name' => $sale->customer_name,
		'amount_due' => to_currency($sale->amount_due),
		'amount_tendered' => to_currency($sale->amount_tendered),
		'change_due' => to_currency($sale->change_due),
		'payment_type' => $sale->payment_type
	];

	if(config('OSPOS')->settings['invoice_enable'])
	{
		$row['invoice_number'] = $sale->invoice_number;
		$row['invoice'] = empty($sale->invoice_number)
			? ''
			: anchor(
				$controller_name."/invoice/$sale->sale_id",
				'<span class="glyphicon glyphicon-list-alt"></span>',
				['title'=>lang('Sales.show_invoice')]
			);
	}

	$row['receipt'] = anchor(
		$controller_name."/receipt/$sale->sale_id",
		'<span class="glyphicon glyphicon-usd"></span>',
		['title' => lang('Sales.show_receipt')]
	);
	$row['edit'] = anchor(
		$controller_name."/edit/$sale->sale_id",
		'<span class="glyphicon glyphicon-edit"></span>',
		[
			'class' => 'modal-dlg print_hide',
			'data-btn-delete' => lang('Common.delete'),
			'data-btn-submit' => lang('Common.submit'),
			'title' => lang($controller_name . '.update')
		]
	);

	return $row;
}

/**
 * Get the html data last row for the sales
 */
function get_sale_data_last_row(ResultInterface $sales): array
{
	$sum_amount_due = 0;
	$sum_amount_tendered = 0;
	$sum_change_due = 0;

	foreach($sales->getResult() as $key => $sale)
	{
		$sum_amount_due += $sale->amount_due;
		$sum_amount_tendered += $sale->amount_tendered;
		$sum_change_due += $sale->change_due;
	}

	return [
		'sale_id' => '-',
		'sale_time' => lang('Sales.total'),
		'amount_due' => to_currency($sum_amount_due),
		'amount_tendered' => to_currency($sum_amount_tendered),
		'change_due' => to_currency($sum_change_due)
	];
}

/**
 * Get the sales payments summary
 */
function get_sales_manage_payments_summary(array $payments): string
{
	$table = '<div id="report_summary">';
	$total = 0;

	foreach($payments as $key => $payment)
	{
		$amount = $payment['payment_amount'];
		$total = bcadd($total, $amount);
		$table .= '<div class="summary_row">' . $payment['payment_type'] . ': ' . to_currency($amount) . '</div>';
	}

	$table .= '<div class="summary_row">' . lang('Sales.total') . ': ' . to_currency($total) . '</div>';
	$table .= '</div>';

	return $table;
}

/**
 * Get the header for the people tabular view
 *
 * @property employee $employee
 * @property session $session
 */
function get_people_manage_table_headers(): string
{
	$headers = [
		['people.person_id' => lang('Common.id')],
		['last_name' => lang('Common.last_name')],
		['first_name' => lang('Common.first_name')],
		['email' => lang('Common.email')],
		['phone_number' => lang('Common.phone_number')]
	];

	$employee = model(Employee::class);
	$session = session();

	if($employee->has_grant('messages', $session->get('person_id')))
	{
		$headers[] = ['messages' => '', 'sortable' => FALSE];
	}

	return transform_headers($headers);
}

/**
 * Get the html data row for the person
 */
function get_person_data_row(object $person): array
{
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'people.person_id' => $person->person_id,
		'last_name' => $person->last_name,
		'first_name' => $person->first_name,
		'email' => empty($person->email) ? '' : mailto($person->email, $person->email),
		'phone_number' => $person->phone_number,
		'messages' => empty($person->phone_number)
			? ''
			: anchor(
				"Messages/view/$person->person_id",
				'<span class="glyphicon glyphicon-phone"></span>',
				[
					'class' => 'modal-dlg',
					'data-btn-submit' => lang('Common.submit'),
					'title'=>lang('Messages.sms_send')
				]
			),
		'edit' => anchor(
			$controller_name."/view/$person->person_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
					'class' => 'modal-dlg',
					'data-btn-submit' => lang('Common.submit'),
					'title'=>lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];
}

/**
 * Get the header for the customer tabular view
 *
 * @property employee $employee
 * @property session $session
 */
function get_customer_manage_table_headers(): string
{
	$headers = [
		['people.person_id' => lang('Common.id')],
		['last_name' => lang('Common.last_name')],
		['first_name' => lang('Common.first_name')],
		['email' => lang('Common.email')],
		['phone_number' => lang('Common.phone_number')],
		['total' => lang('Common.total_spent'), 'sortable' => FALSE]
	];

	$employee = model(Employee::class);
	$session = session();

	if($employee->has_grant('messages', $session->get('person_id')))
	{
		$headers[] = ['messages' => '', 'sortable' => FALSE];
	}

	return transform_headers($headers);
}

/**
 * Get the html data row for the customer
 */
function get_customer_data_row(object $person, object $stats): array
{
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'people.person_id' => $person->person_id,
		'last_name' => $person->last_name,
		'first_name' => $person->first_name,
		'email' => empty($person->email) ? '' : mailto($person->email, $person->email),
		'phone_number' => $person->phone_number,
		'total' => to_currency($stats->total),
		'messages' => empty($person->phone_number)
			? ''
			: anchor(
				"Messages/view/$person->person_id",	//TODO: String interpolation
				'<span class="glyphicon glyphicon-phone"></span>',
				[
					'class' => 'modal-dlg',
					'data-btn-submit' => lang('Common.submit'),
					'title'=>lang('Messages.sms_send')
				]
			),
		'edit' => anchor(
			$controller_name."/view/$person->person_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];
}

/**
 * Get the header for the suppliers tabular view
 *
 * @property employee $employee
 * @property session $session
 */
function get_suppliers_manage_table_headers(): string
{
	$headers = [
		['people.person_id' => lang('Common.id')],
		['company_name' => lang('Suppliers.company_name')],
		['agency_name' => lang('Suppliers.agency_name')],
		['category' => lang('Suppliers.category')],
		['last_name' => lang('Common.last_name')],
		['first_name' => lang('Common.first_name')],
		['email' => lang('Common.email')],
		['phone_number' => lang('Common.phone_number')]
	];

	$employee = model(Employee::class);
	$session = session();

	if($employee->has_grant('messages', $session->get('person_id')))
	{
		$headers[] = ['messages' => ''];
	}

	return transform_headers($headers);
}

/**
 * Get the html data row for the supplier
 */
function get_supplier_data_row(object $supplier): array
{
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'people.person_id' => $supplier->person_id,
		'company_name' => $supplier->company_name,
		'agency_name' => $supplier->agency_name,
		'category' => $supplier->category,
		'last_name' => $supplier->last_name,
		'first_name' => $supplier->first_name,
		'email' => empty($supplier->email) ? '' : mailto($supplier->email, $supplier->email),
		'phone_number' => $supplier->phone_number,
		'messages' => empty($supplier->phone_number)
			? ''
			: anchor(
				"Messages/view/$supplier->person_id",
				'<span class="glyphicon glyphicon-phone"></span>',
				[
					'class'=>"modal-dlg",
					'data-btn-submit' => lang('Common.submit'),
					'title'=>lang('Messages.sms_send')
				]
			),
		'edit' => anchor(
			$controller_name."/view/$supplier->person_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class'=>"modal-dlg",
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];
}

/**
 * Get the header for the items tabular view
 *
 * @property attribute $attribute
 */
function get_items_manage_table_headers(): string
{
	$attribute = model(Attribute::class);

	$definition_names = $attribute->get_definitions_by_flags($attribute::SHOW_IN_ITEMS);	//TODO: this should be made into a constant in constants.php

	$headers = [
		['items.item_id' => lang('Common.id')],
		['item_number' => lang('Items.item_number')],
		['name' => lang('Items.name')],
		['category' => lang('Items.category')],
		['company_name' => lang('Suppliers.company_name')],
		['cost_price' => lang('Items.cost_price')],
		['unit_price' => lang('Items.unit_price')],
		['quantity' => lang('Items.quantity')]
	];

	if(config('OSPOS')->settings['use_destination_based_tax'])
	{
		$headers[] = ['tax_percents' => lang('Items.tax_category'), 'sortable' => FALSE];
	}
	else
	{
		$headers[] = ['tax_percents' => lang('Items.tax_percents'), 'sortable' => FALSE];

	}

	$headers[] = ['item_pic' => lang('Items.image'), 'sortable' => FALSE];

	foreach($definition_names as $definition_id => $definition_name)
	{
		$headers[] = [$definition_id => $definition_name, 'sortable' => FALSE];
	}

	$headers[] = ['inventory' => '', 'escape' => FALSE];
	$headers[] = ['stock' => '', 'escape' => FALSE];

	return transform_headers($headers);
}

/**
 * Get the html data row for the item
 *
 * @property attribute $attribute
 * @property item_taxes $item_taxes
 * @property tax_category $tax_category
 */
function get_item_data_row(object $item): array
{
	$attribute = model(Attribute::class);
	$item_taxes = model(Item_taxes::class);
	$tax_category = model(Tax_category::class);

	if(config('OSPOS')->settings['use_destination_based_tax'])
	{
		if($item->tax_category_id == NULL)	//TODO: === ?
		{
			$tax_percents = '-';
		}
		else
		{
			$tax_category_info = $tax_category->get_info($item->tax_category_id);
			$tax_percents = $tax_category_info->tax_category;
		}
	}
	else
	{
		$item_tax_info = $item_taxes->get_info($item->item_id);
		$tax_percents = '';
		foreach($item_tax_info as $tax_info)
		{
			$tax_percents .= to_tax_decimals($tax_info['percent']) . '%, ';
		}
		// remove ', ' from last item	//TODO: if this won't be added back into the code then it should be deleted.
		$tax_percents = substr($tax_percents, 0, -2);
		$tax_percents = !$tax_percents ? '-' : $tax_percents;
	}

	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	$image = NULL;
	if($item->pic_filename != '')	//TODO: !== ?
	{
		$ext = pathinfo($item->pic_filename, PATHINFO_EXTENSION);
		if($ext == '')	//TODO: Convert to ternary notation. Also === ?
		{
			// legacy
			$images = glob('./uploads/item_pics/' . $item->pic_filename . '.*');
		}
		else
		{
			// preferred
			$images = glob('./uploads/item_pics/' . $item->pic_filename);
		}

		if(sizeof($images) > 0)
		{
			$image .= '<a class=\'rollover\' href=\''. base_url($images[0]) .'\'><img alt=\'Image thumbnail\' src=\''.site_url('items/pic_thumb/' . pathinfo($images[0], PATHINFO_BASENAME)) . '\'></a>';
		}
	}

	if(config('OSPOS')->settings['multi_pack_enabled'])
	{
		$item->name .= NAME_SEPARATOR . $item->pack_name;
	}

	$definition_names = $attribute->get_definitions_by_flags($attribute::SHOW_IN_ITEMS);

	$columns = [
		'items.item_id' => $item->item_id,
		'item_number' => $item->item_number,
		'name' => $item->name,
		'category' => $item->category,
		'company_name' => $item->company_name,
		'cost_price' => to_currency($item->cost_price),
		'unit_price' => to_currency($item->unit_price),
		'quantity' => to_quantity_decimals($item->quantity),
		'tax_percents' => !$tax_percents ? '-' : $tax_percents,
		'item_pic' => $image
	];

	$icons = [
		'inventory' => anchor(
			$controller_name."/inventory/$item->item_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-pushpin"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title' => lang($controller_name . '.count')	//TODO: String interpolation
			]
		),
		'stock' => anchor(
			$controller_name."/count_details/$item->item_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-list-alt"></span>',
			[
				'class' => 'modal-dlg',
				'title' => lang($controller_name . '.details_count')	//TODO: String interpolation
			]
		),
		'edit' => anchor(
			$controller_name."/view/$item->item_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title' => lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];

	return $columns + expand_attribute_values($definition_names, (array) $item) + $icons;
}

/**
 * Get the header for the giftcard tabular view
 */
function get_giftcards_manage_table_headers(): string
{
	$headers = [
		['giftcard_id' => lang('Common.id')],
		['last_name' => lang('Common.last_name')],
		['first_name' => lang('Common.first_name')],
		['giftcard_number' => lang('Giftcards.giftcard_number')],
		['value' => lang('Giftcards.card_value')]
	];

	return transform_headers($headers);
}

/**
 * Get the html data row for the giftcard
 */
function get_giftcard_data_row(object $giftcard): array
{

	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'giftcard_id' => $giftcard->giftcard_id,
		'last_name' => $giftcard->last_name,
		'first_name' => $giftcard->first_name,
		'giftcard_number' => $giftcard->giftcard_number,
		'value' => to_currency($giftcard->value),
		'edit' => anchor(
			$controller_name."/view/$giftcard->giftcard_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];
}

/**
 * Get the header for the item kits tabular view
 */
function get_item_kits_manage_table_headers(): string
{
	$headers = [
		['item_kit_id' => lang('Item_kits.kit')],
		['item_kit_number' => lang('Item_kits.item_kit_number')],
		['name' => lang('Item_kits.name')],
		['description' => lang('Item_kits.description')],
		['total_cost_price' => lang('Items.cost_price'), 'sortable' => FALSE],
		['total_unit_price' => lang('Items.unit_price'), 'sortable' => FALSE]
	];

	return transform_headers($headers);
}

/**
 * Get the html data row for the item kit
 */
function get_item_kit_data_row(object $item_kit): array
{

	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'item_kit_id' => $item_kit->item_kit_id,
		'item_kit_number' => $item_kit->item_kit_number,
		'name' => $item_kit->name,
		'description' => $item_kit->description,
		'total_cost_price' => to_currency($item_kit->total_cost_price),
		'total_unit_price' => to_currency($item_kit->total_unit_price),
		'edit' => anchor(
			$controller_name."/view/$item_kit->item_kit_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];
}

function parse_attribute_values(array $columns, array $row): array
{
	$attribute_values = [];
	foreach($columns as $column)
	{
		if (array_key_exists($column, $row))
		{
			$attribute_value = explode('|', $row[$column]);
			$attribute_values = array_merge($attribute_values, $attribute_value);
		}
	}
	return $attribute_values;
}

function expand_attribute_values(array $definition_names, array $row): array
{
	$values = parse_attribute_values(['attribute_values', 'attribute_dtvalues', 'attribute_dvalues'], $row);

	$indexed_values = [];
	foreach($values as $attribute_value)
	{
		$exploded_value = explode('_', $attribute_value);
		if(sizeof($exploded_value) > 1)
		{
			$indexed_values[$exploded_value[0]] = $exploded_value[1];
		}
	}

	$attribute_values = [];
	foreach($definition_names as $definition_id => $definition_name)
	{
		if(isset($indexed_values[$definition_id]))
		{
			$attribute_value = $indexed_values[$definition_id];
			$attribute_values["$definition_id"] = $attribute_value;
		}
	}

	return $attribute_values;
}

function get_attribute_definition_manage_table_headers(): string
{
	$headers = [
		['definition_id' => lang('Attributes.definition_id')],
		['definition_name' => lang('Attributes.definition_name')],
		['definition_type' => lang('Attributes.definition_type')],
		['definition_flags' => lang('Attributes.definition_flags')],
		['definition_group' => lang('Attributes.definition_group')],
	];

	return transform_headers($headers);
}

function get_attribute_definition_data_row(object $attribute): array
{

	$attribute = model('Attribute');
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	if(count($attribute->definition_flags) == 0)	//TODO: === ?
	{
		$definition_flags = lang('Common.none_selected_text');
	}
	else if($attribute->definition_type == GROUP)
	{
		$definition_flags = "-";
	}
	else
	{
		$definition_flags = implode(', ', $attribute->definition_flags);
	}

	return [
		'definition_id' => $attribute->definition_id,
		'definition_name' => $attribute->definition_name,
		'definition_type' => $attribute->definition_type,
		'definition_group' => $attribute->definition_group,
		'definition_flags' => $definition_flags,
		'edit' => anchor(
			"$controller_name/view/$attribute->definition_id",
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')
			]
		)
	];
}

/**
 * Get the header for the expense categories tabular view
 */
function get_expense_category_manage_table_headers(): string
{
	$headers = [
		['expense_category_id' => lang('Expenses_categories.category_id')],
		['category_name' => lang('Expenses_categories.name')],
		['category_description' => lang('Expenses_categories.description')]
	];

	return transform_headers($headers);
}

/**
 * Gets the html data row for the expenses category
 */
function get_expense_category_data_row(object $expense_category): array
{
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'expense_category_id' => $expense_category->expense_category_id,
		'category_name' => $expense_category->category_name,
		'category_description' => $expense_category->category_description,
		'edit' => anchor(
			$controller_name."/view/$expense_category->expense_category_id",	//TODO: String interpolation
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')	//TODO: String interpolation
			]
		)
	];
}


/**
 * Get the header for the expenses tabular view
 */
function get_expenses_manage_table_headers(): string
{
	$headers = [
		['expense_id' => lang('Expenses.expense_id')],
		['date' => lang('Expenses.date')],
		['supplier_name' => lang('Expenses.supplier_name')],
		['supplier_tax_code' => lang('Expenses.supplier_tax_code')],
		['amount' => lang('Expenses.amount')],
		['tax_amount' => lang('Expenses.tax_amount')],
		['payment_type' => lang('Expenses.payment')],
		['category_name' => lang('Expenses_categories.name')],
		['description' => lang('Expenses.description')],
		['created_by' => lang('Expenses.employee')]
	];

	return transform_headers($headers);
}

/**
 * Gets the html data row for the expenses
 */
function get_expenses_data_row(object $expense): array
{
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'expense_id' => $expense->expense_id,
		'date' => to_datetime(strtotime($expense->date)),
		'supplier_name' => $expense->supplier_name,
		'supplier_tax_code' => $expense->supplier_tax_code,
		'amount' => to_currency($expense->amount),
		'tax_amount' => to_currency($expense->tax_amount),
		'payment_type' => $expense->payment_type,
		'category_name' => $expense->category_name,
		'description' => $expense->description,
		'created_by' => $expense->first_name.' '. $expense->last_name,
		'edit' => anchor(
			$controller_name."/view/$expense->expense_id",
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')
			]
		)
	];
}

/**
 * Get the html data last row for the expenses
 */
function get_expenses_data_last_row(object $expense): array
{
	$table_data_rows = '';	//TODO: This variable is never used
	$sum_amount_expense = 0;
	$sum_tax_amount_expense = 0;

	foreach($expense->getResult() as $key => $expense)
	{
		$sum_amount_expense += $expense->amount;
		$sum_tax_amount_expense += $expense->tax_amount;
	}

	return [
		'expense_id' => '-',
		'date' => lang('Sales.total'),
		'amount' => to_currency($sum_amount_expense),
		'tax_amount' => to_currency($sum_tax_amount_expense)
	];
}

/**
 * Get the expenses payments summary
 */
function get_expenses_manage_payments_summary(array $payments, ResultInterface $expenses): string	//TODO: $expenses is passed but never used.
{
	$table = '<div id="report_summary">';

	foreach($payments as $key => $payment)
	{
		$amount = $payment['amount'];
		$table .= '<div class="summary_row">' . $payment['payment_type'] . ': ' . to_currency($amount) . '</div>';
	}

	$table .= '</div>';

	return $table;
}


/**
 * Get the header for the cashup tabular view
 */
function get_cashups_manage_table_headers(): string
{
	$headers = [
		['cashup_id' => lang('Cashups.id')],
		['open_date' => lang('Cashups.opened_date')],
		['open_employee_id' => lang('Cashups.open_employee')],
		['open_amount_cash' => lang('Cashups.open_amount_cash')],
		['transfer_amount_cash' => lang('Cashups.transfer_amount_cash')],
		['close_date' => lang('Cashups.closed_date')],
		['close_employee_id' => lang('Cashups.close_employee')],
		['closed_amount_cash' => lang('Cashups.closed_amount_cash')],
		['note' => lang('Cashups.note')],
		['closed_amount_due' => lang('Cashups.closed_amount_due')],
		['closed_amount_card' => lang('Cashups.closed_amount_card')],
		['closed_amount_check' => lang('Cashups.closed_amount_check')],
		['closed_amount_total' => lang('Cashups.closed_amount_total')]
	];

	return transform_headers($headers);
}

/**
 * Gets the html data row for the cashups
 */
function get_cash_up_data_row(object $cash_up): array
{
	$router = service('router');
	$controller_name = strtolower($router->controllerName());

	return [
		'cashup_id' => $cash_up->cashup_id,
		'open_date' => to_datetime(strtotime($cash_up->open_date)),
		'open_employee_id' => $cash_up->open_first_name . ' ' . $cash_up->open_last_name,
		'open_amount_cash' => to_currency($cash_up->open_amount_cash),
		'transfer_amount_cash' => to_currency($cash_up->transfer_amount_cash),
		'close_date' => to_datetime(strtotime($cash_up->close_date)),
		'close_employee_id' => $cash_up->close_first_name . ' ' . $cash_up->close_last_name,
		'closed_amount_cash' => to_currency($cash_up->closed_amount_cash),
		'note' => $cash_up->note ? $CI->lang->line('common_yes') : $CI->lang->line('common_no'),
		'closed_amount_due' => to_currency($cash_up->closed_amount_due),
		'closed_amount_card' => to_currency($cash_up->closed_amount_card),
		'closed_amount_check' => to_currency($cash_up->closed_amount_check),
		'closed_amount_total' => to_currency($cash_up->closed_amount_total),
		'edit' => anchor(
			$controller_name."/view/$cash_up->cashup_id",
			'<span class="glyphicon glyphicon-edit"></span>',
			[
				'class' => 'modal-dlg',
				'data-btn-submit' => lang('Common.submit'),
				'title'=>lang($controller_name . '.update')
			]
		)
	];
}
