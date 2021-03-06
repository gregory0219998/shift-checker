<?php
// MODIFIED BY GREGORST
echo "[ FORKING ]\n";
echo "\t\t\tGoing to check for forked status now...\n";

// Set the database to save our counts to
    $db = new SQLite3($database) or die("[ FORKING ] Unable to open database");
 
// Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS $table (
                    id INTEGER PRIMARY KEY,  
                    counter INTEGER,
                    time INTEGER)");

// Let's check if any rows exists in our table
    $check_exists = $db->query("SELECT count(*) AS count FROM $table");
    $row_exists   = $check_exists->fetchArray();
    $numExists    = $row_exists['count'];

    // If no rows exist in our table, add one
    	if($numExists < 1){
        	
        	// Echo something to our log file
        	echo "\t\t\tNo rows exist in our table to update the counter...Adding a row for you.\n";
        	
        	$insert = "INSERT INTO $table (counter, time) VALUES ('0', time())";
        	$db->exec($insert) or die("[ FORKING ] Failed to add row!");
      	
      	}

// Tail lisk.log
	$last = tailCustom($lisklog, $linestoread);

// Count how many times the fork message appears in the tail
	$count = substr_count($last, $msg);

// Get counter value from our database
    $check_count 	  = $db->query("SELECT * FROM $table LIMIT 1");
    $row          	= $check_count->fetchArray();
    $counter      	= $row['counter'];

// If counter + current count is greater than $max_count, take action...
    if (($counter + $count) >= $max_count) {

        // If lisk-snapshot directory exists..
       
          if($telegramEnable === true){
            $Tmsg = "Hit max_count on ".gethostname().". I am going to restore from a snapshot.";
            passthru("curl -s -d 'chat_id=$telegramId&text=$Tmsg' $telegramSendMessage >/dev/null");
          }

          // Perform snapshot restore
          passthru("cd $pathtoapp && bash lisk.sh stop");
          passthru("cd $pathtoapp && bash lisk.sh rebuild -u https://snapshot.lisknode.io/");
          passthru("cd $pathtoapp && bash lisk.sh start");

          // Reset counters
          echo "\t\t\tFinally, I will reset the counter for you...\n";
          $query = "UPDATE $table SET counter='0', time=time()";
          $db->exec($query) or die("[ FORKING ] Unable to set counter to 0!");
        }
// MODIFIED BY GREGORST
// If counter + current count is not greater than $max_count, add current count to our database...
     else {

	    $query = "UPDATE $table SET counter=counter+$count, time=time()";
    	$db->exec($query) or die("[ FORKING ] Unable to plus the counter!");

    	echo "\t\t\tCounter ($counter) + current count ($count) is not sufficient to restore from snapshot. Need: $max_count \n";

    	// Check snapshot setting
    	if($createsnapshot === false){
    		echo "\t\t\tSnapshot setting is disabled.\n";
    	}

    	// If counter + current count are smaller than $max_count AND option $createsnapshot is true, create a new snapshot
    	if(($counter + $count) < $max_count && $createsnapshot === true){
    		
    		echo "\t\t\tIt's safe to create a daily snapshot and the setting is enabled.\n";
    		echo "\t\t\tLet's check if a snapshot was already created today...\n";
    		
    		// Check if path to lisk-snapshot exists..
        if(file_exists($snapshotDir)){
          
          $snapshots = glob($snapshotDir.'snapshot/lisk_db'.date("d-m-Y").'*.snapshot.tar');
          if (!empty($snapshots)) {
        
            echo "\t\t\tA snapshot for today already exists:\n";
              echo "\t\t\t".$snapshots[0]."\n";
            
            echo "\t\t\tGoing to remove snapshots older than $max_snapshots days...\n";
              $files = glob($snapshotDir.'snapshot/lisk_db*.snapshot.tar');
              foreach($files as $file){
                if(is_file($file)){
                    if(time() - filemtime($file) >= 60 * 60 * 24 * $max_snapshots){
                      if(unlink($file)){
                        echo "\t\t\tDeleted snapshot $file\n";
                      }
                    }
                }
              }

            echo "\t\t\tDone!\n";
        
          }else{

            echo "\t\t\tNo snapshot exists for today, I will create one for you now!\n";
              // MODIFIED BY GREGORST
            ob_start();
            $create = passthru("cd $snapshotDir && ./lisk-snapshot.sh create");
            $check_createoutput = ob_get_contents();
            ob_end_clean();

            // If buffer contains "OK snapshot created successfully"
            if(strpos($check_createoutput, 'OK snapshot created successfully') !== false){
            
                echo "\t\t\tDone!\n";
              
              if($telegramEnable === true){
                  $Tmsg = "Created daily snapshot on ".gethostname().".";
                  passthru("curl -s -d 'chat_id=$telegramId&text=$Tmsg' $telegramSendMessage >/dev/null");
              }

            }

          }
        }else{
          // Path to lisk-snapshot does not exist..
          echo "\t\t\tYou have lisk-snapshot enabled, but the path to lisk-snapshot does not seem to exist.\n
                \t\t\tDid you install lisk-snapshot?\n";
        }

    	}

    }
