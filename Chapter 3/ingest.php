<?php


$db_host="127.0.0.1";
$db_user="root";
$db_pass="dba";

# gcp cost database 
$db_name="gcp_costs";

# the name of the final database table for costs
$dtl_costs_table="gcp_billing_details";

# the name of the temporary database table for costs
$dtl_costs_table_victims="gcp_billing_victim";


if (count($argv)<2){
  echo "ERROR : invocation without cost detail file.\n";
  echo "USAGE : ".$argv[0]." <fullpath to CSV file>\n";

  exit;
}else
{
  echo "INFO : ingesting GCP billing file : ".$argv[1]."\n";
}


$db_link = mysqli_init();
mysqli_options($db_link, MYSQLI_OPT_LOCAL_INFILE, true);
mysqli_real_connect($db_link, $db_host, $db_user, $db_pass,$db_name);

if (!$db_link) {
    die('ERROR : Could not connect: ');
}
echo "INFO : Connected successfully to db : ".$db_name."\n";


# ingest CSV in victim table
# check if the file appear to be CSV file with headings from GCP
$ingest_from_file=true;
if ($ingest_from_file) { 
  $sql_truncate_victims="truncate table ".$dtl_costs_table_victims.";";

  # check if the CSV start with 8 lines heading
  $skip_lines=8;
  $invoice_lines=file($argv[1]);
  if ( ! (substr(strtolower($invoice_lines[0]),0,7)==="invoice")){
    $skip_line=1;
    echo "INFO : CSV does not seems to be an invoice, skipping only 1 line\r\n";
  }else{
    echo "INFO : CSV seems to be an invoice with heading, skipping ".$skip_lines." lines\r\n";
  }

  $sql_ingest="LOAD DATA INFILE  \"".$argv[1]."\"  INTO TABLE ".$dtl_costs_table_victims." FIELDS TERMINATED BY ','  OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\\n' IGNORE ".$skip_lines." ROWS; ";

  echo "DEBUG: $sql_truncate_victims \n";
  $result_truncate=mysqli_query( $db_link, $sql_truncate_victims );
  if (mysqli_errno($db_link)) {
   echo "ERROR : mysql error ".mysqli_errno($db_link)."\n";
   die();
  }

  echo "DEBUG : $sql_ingest \n";
  $result_ingestion=mysqli_query( $db_link, $sql_ingest );

  if (mysqli_errno($db_link)) {
    echo "ERROR : mysql error ".mysqli_errno($db_link)."\n";
    die();
  }
}

# CSV costs file contain TAX or COMMON COSTS records without date.
# set every cost without a date (eg: taxes) to first day of the month of invoice period, base on the last day of the period
echo "INFO : setting dates on record without dates - e.g. TAXEX records does not have a specific DATE\n";
echo "       this import procedure will set TAXES start_usage and end_usage field to the first day of the period.\n";
echo "\n";
echo "INFO : setting dates on taxes records.\n";
$sql_fix_invalid_tax_date_endfield="update ".$dtl_costs_table_victims." set usage_end_date = (select date(concat(year(max(date(usage_end_date))),concat('-',concat(month(max(date(usage_end_date))),'-01'))))   from ".$dtl_costs_table_victims." where length(usage_end_date)>2 ) where (length(usage_end_date)<1);";
$sql_fix_invalid_tax_date_startfield="update ".$dtl_costs_table_victims." set usage_start_date = (select date(concat(year(max(date(usage_end_date))),concat('-',concat(month(max(date(usage_end_date))),'-01'))))   from ".$dtl_costs_table_victims." where length(usage_end_date)>2 ) where (length(usage_start_date)<1);";

echo $sql_fix_invalid_tax_date_endfield."\n";
$result_set_first_day=mysqli_query( $db_link, $sql_fix_invalid_tax_date_endfield);
if (mysqli_errno($db_link)) {
  echo "ERROR : mysql error ".mysqli_errno($db_link)."\n";
  die();
}
echo $sql_fix_invalid_tax_date_startfield."\n";
$result_set_first_day=mysqli_query( $db_link, $sql_fix_invalid_tax_date_startfield);
if (mysqli_errno($db_link)) {
  echo "ERROR : mysql error ".mysqli_errno($db_link)."\n";
  die();
}


echo "INFO : the CSV file does not contain daily data, but to simplify BI reports we need daily costs\n";
echo "       to avoid working with date/time intervals\n";
echo "       This script will distribute equally (FLAT) the costs on all the costs from start_usage to end_usage\n\n";
echo "       eg: 24euros  from start_usage=2021-10-19 to end_usage=2021-10-30\n";
echo "           will generate 12 'daily' records of 2euros each\n\n";
echo "WARNING : of course this is a simplification, since you can have a peak on a single day, but without\n";
echo "          daily costs from GCP this is the only alternative to transform grouped rows into a flat table.\n";
echo "          This will enable a daily-based BI model even if we don't have daily data.\n";
$sql_victims="select * from ".$dtl_costs_table_victims.";";
$result_victims=mysqli_query( $db_link, $sql_victims);
if ($result_victims) {
  while ($row = mysqli_fetch_assoc($result_victims)) {
    $start_date=$row['usage_start_date'];
    $end_date=$row['usage_end_date'];

    if ($start_date==$end_date){

      if (strlen($start_date)<2){ 
        echo "WARNING : found record with start/end date uncorrect (start_date=".$start_date." end_date=".$end_date.")";
      }else{
        echo "INFO : found line start=end=".$start_date."\n";	    
        $sql_insert="insert into ".$dtl_costs_table." ( billing_account_name,billing_account_id, project_name, project_number, project_id, service_description, service_id ,sku_description, sku_id ,credit_type ,label,cost_type ,usage_start_date ,usage_end_date ,usage_amount ,usage_unit,unrounded_cost ,cost, fake_date,split_daily_costs) values ('".mysqli_real_escape_string($db_link,$row['billing_account_name'])."','".mysqli_real_escape_string($db_link,$row['billing_account_id'])."','".mysqli_real_escape_string($db_link,$row['project_name'])."','".mysqli_real_escape_string($db_link,$row['project_number'])."','".mysqli_real_escape_string($db_link,$row['project_id'])."','".mysqli_real_escape_string($db_link,$row['service_description'])."','".mysqli_real_escape_string($db_link,$row['service_id'])."','".mysqli_real_escape_string($db_link,$row['sku_description'])."','".mysqli_real_escape_string($db_link,$row['sku_id'])."','".mysqli_real_escape_string($db_link,$row['credit_type'])."','".mysqli_real_escape_string($db_link,$row['label'])."','".mysqli_real_escape_string($db_link,$row['cost_type'])."','".mysqli_real_escape_string($db_link,$row['usage_start_date'])."','".mysqli_real_escape_string($db_link,$row['usage_end_date'])."','".mysqli_real_escape_string($db_link,$row['usage_amount'])."','".mysqli_real_escape_string($db_link,$row['usage_unit'])."',".mysqli_real_escape_string($db_link,$row['unrounded_cost']).",".mysqli_real_escape_string($db_link,$row['cost']).",'".mysqli_real_escape_string($db_link,$row['usage_start_date'])."',".mysqli_real_escape_string($db_link,$row['cost'])." );";

        $result_insert=mysqli_query( $db_link, $sql_insert);
        if (!$result_insert){ 
          echo "ERROR : failed to insert.\n";
  	  echo "DEBUG : ".$sql_insert;
	  echo "\n";
        }     
      }


    }else
    {
      
      //son billato per un range
      $earlier = new DateTime($start_date);
      $later = new DateTime($end_date);

      $abs_diff = $later->diff($earlier)->format("%a"); 

      echo "INFO : found line with date range ".$start_date." to ".$end_date." = ".$abs_diff." days.\n";
      echo "       original cost = ".$row["cost"]." splitted cost = ".$row["cost"]/$abs_diff. ".\n";

      for ($i=0; $i<$abs_diff; $i++){
	$rel_date=(clone $earlier)->modify("+".$i." day")->format('Y-m-d');

	$sql_insert="insert into ".$dtl_costs_table." ( billing_account_name,billing_account_id, project_name, project_number, project_id, service_description, service_id ,sku_description, sku_id ,credit_type ,label, cost_type ,usage_start_date ,usage_end_date ,usage_amount ,usage_unit,unrounded_cost ,cost, fake_date,split_daily_costs) values ('".mysqli_real_escape_string($db_link,$row['billing_account_name'])."','".mysqli_real_escape_string($db_link,$row['billing_account_id'])."','".mysqli_real_escape_string($db_link,$row['project_name'])."','".mysqli_real_escape_string($db_link,$row['project_number'])."','".mysqli_real_escape_string($db_link,$row['project_id'])."','".mysqli_real_escape_string($db_link,$row['service_description'])."','".mysqli_real_escape_string($db_link,$row['service_id'])."','".mysqli_real_escape_string($db_link,$row['sku_description'])."','".mysqli_real_escape_string($db_link,$row['sku_id'])."','".mysqli_real_escape_string($db_link,$row['credit_type'])."','".mysqli_real_escape_string($db_link,$row['label'])."','".mysqli_real_escape_string($db_link,$row['cost_type'])."','".mysqli_real_escape_string($db_link,$row['usage_start_date'])."','".mysqli_real_escape_string($db_link,$row['usage_end_date'])."','".mysqli_real_escape_string($db_link,$row['usage_amount'])."','".mysqli_real_escape_string($db_link,$row['usage_unit'])."',".mysqli_real_escape_string($db_link,$row['unrounded_cost']).",".mysqli_real_escape_string($db_link,$row['cost']).",'".$rel_date."',".mysqli_real_escape_string($db_link,$row['cost']/$abs_diff)." );";

      
        $result_insert=mysqli_query( $db_link, $sql_insert);


        if (!$result_insert){
            echo "ERROR : failed to insert.\n";
            echo "DEBUG : ".$sql_insert;
            echo "\n";
        }else{
            echo "       inserted ".$start_date." +".$i."day, cost = ".$row["cost"]/$abs_diff."\n";
        }

      }
      	

    }

  }

}



mysqli_close($db_link);
?>
