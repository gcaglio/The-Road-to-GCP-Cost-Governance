<?php

$db_host="localhost";
$db_user="root";
$db_pass="dba";

# new database to be created
$db_name="gcp_costs";

# the name of the temporary database table for costs
$db_csvcost_victim_table="gcp_billing_victim";
# the name of the final database table for costs
$db_csvcost_table="gcp_billing_details";

## 20220715 G.Caglio - actually not supported for GCP
##
# the name of the final table with the splitted costs by Projects
##$db_splittedcosts_table="gcp_billing_splitted";

$link = mysqli_connect( $db_host, $db_user, $db_pass);
if (!$link) {
  $err=mysqli_connect_errno();
  echo "ERROR : unable to connect to db (".$err.")\r\n";
  return 2;
}

# create database
$sql_dbcreate = "CREATE DATABASE ".$db_name;
if(mysqli_query($link, $sql_dbcreate)){
    echo "INFO : Database created successfully\r\n";
} else{
    echo "ERROR : Could not able to execute $sql_dbcreate (".mysqli_error($link).")\r\n";
}

# select the newly created db
$db_selected = mysqli_select_db( $link, $db_name );
if (!$db_selected) {
  echo "ERROR : unable to use ".$db_name." database (".mysqli_error($link).")";
  return 3;
}

# create a table for the GCP cost 1:1 with the fields from the CSV file.
$sql_victimcreate = "CREATE table ".$db_csvcost_victim_table." (
			  billing_account_name varchar(200) NOT NULL DEFAULT '',
			  billing_account_id varchar(200) NOT NULL DEFAULT '',
			  project_name varchar(200) NOT NULL DEFAULT '',
			  project_id varchar(200) NOT NULL DEFAULT '',
			  project_number varchar(200) NOT NULL DEFAULT '',
			  project_hierarcy varchar(100) NOT NULL DEFAULT '',
			  service_description varchar(1000) NOT NULL DEFAULT '',
			  service_id varchar(200) NOT NULL DEFAULT '',
			  sku_description varchar(200) NOT NULL DEFAULT '',
			  sku_id varchar(200) NOT NULL DEFAULT '',
			  credit_type varchar(200) NOT NULL DEFAULT '',
			  credit_id varchar(200) NOT NULL DEFAULT '',
			  credit_name varchar(200) NOT NULL DEFAULT '',
			  label varchar(100) NOT NULL DEFAULT '',
			  cost_type varchar(200) NOT NULL DEFAULT '',
			  usage_start_date varchar(200) NOT NULL DEFAULT '',
			  usage_end_date varchar(200) NOT NULL DEFAULT '',
			  usage_amount varchar(200) NOT NULL DEFAULT '',
			  usage_unit varchar(200) NOT NULL DEFAULT '',
			  cost_in_micros double NOT NULL DEFAULT 0,
			  list_cost varchar(100) NOT NULL DEFAULT '',
			  unrounded_cost varchar(100) NOT NULL DEFAULT '',
			  cost double NOT NULL DEFAULT 0
		)";


if(mysqli_query($link, $sql_victimcreate)){
  echo "INFO : victim table ".$db_csvcost_victim_table." created succesfully\r\n";
} else{
  echo "ERROR : Could not execute $sql_victimcreate (".mysqli_error($link).")\r\n";
  return 4;
}


# create a table for the GCP elaborated csv costs.
$sql_costcreate = "CREATE table ".$db_csvcost_table." (
			  billing_account_name varchar(200) NOT NULL DEFAULT '',
			  billing_account_id varchar(200) NOT NULL DEFAULT '',
			  project_name varchar(200) NOT NULL DEFAULT '',
			  project_id varchar(200) NOT NULL DEFAULT '',
			  project_number varchar(200) NOT NULL DEFAULT '',
			  service_description varchar(1000) NOT NULL DEFAULT '',
			  service_id varchar(200) NOT NULL DEFAULT '',
			  sku_description varchar(200) NOT NULL DEFAULT '',
			  sku_id varchar(200) NOT NULL DEFAULT '',
			  credit_type varchar(200) NOT NULL DEFAULT '',
			  credit_id varchar(200) NOT NULL DEFAULT '',
			  credit_name varchar(200) NOT NULL DEFAULT '',
			  label varchar(100) NOT NULL DEFAULT '',
			  cost_type varchar(200) NOT NULL DEFAULT '',
			  usage_start_date varchar(200) NOT NULL DEFAULT '',
			  usage_end_date varchar(200) NOT NULL DEFAULT '',
			  usage_amount varchar(200) NOT NULL DEFAULT '',
			  usage_unit varchar(200) NOT NULL DEFAULT '',
			  cost_in_micros double NOT NULL DEFAULT 0,
			  list_cost varchar(100) NOT NULL DEFAULT '',
			  unrounded_cost double NOT NULL DEFAULT 0,
			  cost double NOT NULL DEFAULT 0,
			  fake_date date NOT NULL DEFAULT '0000-00-00',
			  split_daily_costs double NOT NULL DEFAULT 0,
			  KEY idx_fakedate (fake_date),
			  KEY idx_projectname (project_name)
		)";


if(mysqli_query($link, $sql_costcreate)){
  echo "INFO : victim table ".$db_csvcost_table." created succesfully\r\n";
} else{
  echo "ERROR : Could not execute $sql_costcreate (".mysqli_error($link).")\r\n";
  return 4;
}

### 20220715 G.Caglio - actually not supported for GCP
###
#$sql_splittedcosts="CREATE table ".$db_splittedcosts_table." ( 
#	                Date date not null default '0000-00-00',
#			BsnApp varchar(20) not null default '',
#			BsnApp_Tag varchar(200) not null default '',
#			Landscape varchar(200) not null default '',
#			ResourceId varchar(2000) not null default '',
#                       ResourceGroup varchar(200) not null default '',
#			MeterCategory varchar(60) not null default '',
#			MeterSubCategory varchar(60) not null default '',
#			MeterName varchar(50) not null default '',
#			SplittedCost double not null default 0,
#                        FullCost double not null default 0
#		        )";

#if(mysqli_query($link, $sql_splittedcosts)){
#  echo "INFO : victim table ".$db_splittedcosts_table." created succesfully\r\n";
#} else{
#  echo "ERROR : Could not execute $sql_splittedcosts (".mysqli_error($link).")\r\n";
#  return 4;
#}


mysqli_close($link);
return 0;
?>


