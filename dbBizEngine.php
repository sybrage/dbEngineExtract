<? php

  function dbConnection($dbName) {
  return mysqli_connect("localhost", "devDB", [md5String], $dbName);
}

function readData($dbName, $tableName, $fieldNames, $conditions = "",
                  $order = "", $orderField = "", $queryLimit = "",
                  $readLinkedData = true) {  //~readdata~
  global $dbError;
  $result = false;

  $dbase = dbConnection($dbName);
  if ($dbase) {

    $query = "SELECT " . $fieldNames . " FROM " . $tableName;

    if (is_array($conditions)) {
      if ($conditions['conjunction'] == "or") {
        array_shift($conditions);
        $conjunction = " OR ";
      } else {
        $conjunction = " AND ";
      }

      $queryConditions = queryBuilder($dbase, $tableName, $conditions, $conjunction);
      $query .= " WHERE " . $queryConditions;
    }

    if ($order != "" && $orderField != "")
      $query .= " ORDER BY " . $orderField . " " . $order ;

    if ($queryLimit != "")
      $query .= " LIMIT " . $queryLimit;

    $queryResult = mysqli_query($dbase, $query);

    if ($queryResult) {
      $result = array();
      while($row = mysqli_fetch_assoc($queryResult)) {
        $result[] = $row;
      }
      if ($readLinkedData) {
        foreach($result as &$entry) {
          foreach($entry as $key => $value) {
            if (instr($key, "_")) {
              readLinkedTable($dbase, $entry);
              break;
            }
          }
        }
      }
    }

    $dbError = mysqli_error($dbase);
    mysqli_close($dbase);
  }

  return $result;
}

function smartRead($dbName, $tableName, $fieldNames,
                   $conditions = "", $limitOrder = "",
                   $readImages = false) {  //~srd
  global $dbError;
  $result = false;

  $dbase = dbConnection($dbName);
  if ($dbase) {
    $query = "SELECT " . $fieldNames . " FROM " . $tableName;
    if (is_array($conditions)) {
      if ($conditions['conjunction'] == "or") {
        array_shift($conditions);
        $conjunction = " OR ";
      } else {
        $conjunction = " AND ";
      }
      $queryConditions = queryBuilder($dbase, $tableName, $conditions, $conjunction);
      $query .= " WHERE " . $queryConditions;
    }
    if ($limitOrder != "")
      $query .= " " . $limitOrder;
    $queryResult = mysqli_query($dbase, $query);

    if ($queryResult) {
      $result = array();
      while($row = mysqli_fetch_assoc($queryResult)) {
        $result[] = $row;
      }
      foreach($result as &$entry) {
        foreach($entry as $key => $value) {
          if (instr($key, "_")) {
            $keys = explode("_", $key);
            $subKey = $keys[0];
            $tableName = $keys[1];
            $images = (instr($keys[1], "images"));

            if ($images) {
              if (!$readImages) continue;
            }
            $queryResult = smartRead($dbName, $tableName, "*",
                                     array("id" => $value), "", $readImages);
            if ($queryResult) {
              $entry[$subKey] = ($images) ?
                "data:image/".$queryResult[0]["imageType"].";base64," .
                base64_encode($queryResult[0]["image"]) : $queryResult[0];
            }
          }
        }
      }
    }

    $dbError = mysqli_error($dbase);
    mysqli_close($dbase);
  }

  return $result;
}

function readLinkedTable($dbase, &$queries) {  //~rl
  foreach($queries as $key => $value) {
    if (instr($key, "_")) {
      $keyData = explode("_", $key);
      $images = (instr($keyData[1], "images"));
      $query = ($images) ?
        ("SELECT * FROM " .  $keyData[1] . " WHERE id=" . $value) :
      ("SELECT " . $keyData[2] . " FROM " .  $keyData[1] . " WHERE id=" . $value);
      $queryResult = mysqli_query($dbase, $query);
      if ($queryResult) {
        while($row = mysqli_fetch_assoc($queryResult)) {
          foreach($row as $subKey => $subValue) {
            $queries[$keyData[0]] = ($images) ?
              "data:image/".$row["imageType"].";base64,".
              base64_encode($row["image"]) : $subValue;
          }
        }
      }
    }
  }
  return $result;
}

function updateData($dbName, $tableName,
                    &$valueAssignments, $conditions = "") {
  global $dbError;
  $result = false;
  $dbase = dbConnection($dbName);

  if ($dbase) {
    $deleteFlag = deleteQuery($valueAssignments);

    if (linkedImages($valueAssignments) && !$deleteFlag)
      updateLinkedTable($dbase, $valueAssignments);  // update linked images

    $queryValueAssignments = queryBuilder($dbase, $tableName, $valueAssignments, ",");

    if ($conditions != "" && is_array($conditions)) {
      $queryConditions = queryBuilder($dbase, $tableName, $conditions, " AND ");
      $query = "UPDATE " . $tableName . " SET "
        . $queryValueAssignments . " WHERE " . $queryConditions;
      $result = mysqli_query($dbase, $query);
    }

    if ((mysqli_affected_rows($dbase) < 1 || !$result) && !$deleteFlag) {
      $queryValueAssignments = queryBuilder($dbase, $tableName, $valueAssignments, ",", true);
      $query = "INSERT INTO " . $tableName . $queryValueAssignments;
      $result = mysqli_query($dbase, $query);
      $valueAssignments['insertionIndex'] = mysqli_insert_id($dbase);
    }

    $dbError = mysqli_error($dbase);
    mysqli_close($dbase);
  }

  return $result;
}

function insertData($dbName, $tableName, $valueAssignments) {
  global $dbError;
  $result = false;
  $dbase = dbConnection($dbName);

  if ($dbase) {
    $queryValueAssignments = queryBuilder($dbase, $tableName, $valueAssignments, ",");
    $query = "INSERT INTO " . $tableName . $queryValueAssignments;
    $result = mysqli_query($dbase, $query);
    if ($result && (mysqli_affected_rows($dbase) > 0)) {
      $result = true;
    }
  }
}

function deleteData($dbName, $tableName, $conditions) {
  global $dbError;
  $result = false;
  $dbase = dbConnection($dbName);

  if ($dbase) {
    if ($conditions != "" && is_array($conditions)) {
      $queryConditions = queryBuilder($dbase, $tableName, $conditions, " AND ");
      $query = "DELETE FROM " . $tableName . " WHERE " . $queryConditions;
      $result = mysqli_query($dbase, $query);

      if ($result && (mysqli_affected_rows($dbase) > 0)) {
        $result = true;
      }
    }
  }
}

function embedFILES($keyName, $image, $imageType) {
  $_FILES[$keyName]["size"] = 1;
  $_FILES[$keyName]["embed"] = 1;
  $_FILES[$keyName]["base64"] = base64_decode($image);
  $_FILES[$keyName]["type"] = "image/".$imageType;
}

function updateLinkedTable($dbase, &$queries) {  //~upl
  global $dbError;

  foreach($queries as $key => $value) {

    if (instr($key, "_")) {
      $keyData = explode("_", $key);
      $images = (instr($keyData[1], "images"));

      if ($images) {
        $imageFile = $_FILES[$keyData[0]];

        if (!empty($imageFile) && $imageFile["size"] > 0) {
          $image = ($imageFile["embed"] == 1) ?
            $imageFile["base64"] : file_get_contents($_FILES[$keyData[0]]["tmp_name"]);
          $imageType = explode("/", $_FILES[$keyData[0]]["type"]);
          $imageType = (count($imageType) > 1) ? $imageType[1] : $imageType[0];
          $image = mysqli_escape_string($dbase, $image);
          $imageType = mysqli_escape_string($dbase, $imageType);
          $query = "UPDATE " . $keyData[1] . " SET imageType='" .
            $imageType . "', image='" . $image . "' WHERE id=" . (int)$queries[$key];

        } else {
          continue;
        }
      } else {
        $query = "UPDATE " . $keyData[1] . " SET " . $keyData[2]
          . "='" . $queries[$key] . "' WHERE id=" . (int)$value;
      }
      $result =  mysqli_query($dbase, $query);
      $updated = mysqli_affected_rows($dbase);

      if ($updated == 0) {
        if ($images) {
          $query = "INSERT INTO " . $keyData[1]
            . " (imageType, image) VALUES ('" . $imageType . "','" . $image . "')";
        } else {
          $query = "INSERT INTO " . $keyData[1]
            . " (" . $keyData[2] . ") VALUES ('" . $queries[$key] . "')";
        }
        $result = mysqli_query($dbase, $query);
        $value = mysqli_insert_id($dbase);
      }
      $queries[$key] = $value;
    }
  }

  $dbError = mysqli_error($dbase);
  return $result;
}

function updateImage($dbName, $tableName, $type, $image, &$index) {
  $result = false;
  $dbase = dbConnection($dbName);

  if ($dbase & $image != null) {
    $image = base64_decode($image);
    $image = mysqli_escape_string($dbase, $image);

    $query = "UPDATE " . $tableName . " SET imageType='" . $type
      . "', image='" . $image . "' WHERE id=" .(int)$index;
    $result = mysqli_query($dbase, $query);

    if (mysqli_affected_rows($dbase) < 1 || !$result) {
      $query = "INSERT INTO " . $tableName .
        " (imageType, image) VALUES ('" . $type . "','" . $image . "')";

      $result = mysqli_query($dbase, $query);
      $index = mysqli_insert_id($dbase);
    }

    $dbError = mysqli_error($dbase);
    mysqli_close($dbase);
  }

  return $result;
}

function queryBuilder($dbase, $tableName, $array,
                      $conjunction, $insert = false) {  //~qrb
  $result = $queryString = $queryColumns = $queryValues = "";

  foreach($array as $key => $value) {
    if ($insert) {
      $queryColumns .= $key . ",";
      $queryValues .= (columnIsNumeric($dbase, $tableName, $key))
        ? floatval($value) . "," : "'" . $value . "'," ;
    } else {
      if (instr($key, ">")) {
        //if (strrpos($key, ">") !== false) {
        $keyData = explode(">", $key);
        $key = $keyData[0];
      }
      $queryString .= (columnIsNumeric($dbase, $tableName, $key))
        ? $key . "=" . floatval($value) . $conjunction
        : $key . "='" . $value . "'" . $conjunction ;
    }
  }

  $result = ($insert)
    ? "(" . substr($queryColumns, 0, -1)
    . ") VALUES (" . substr($queryValues, 0, -1) . ")"
    : substr($queryString, 0, -(strlen($conjunction))) ;

  return $result;
}

function linkedTable($queries) {
  $result = false;
  foreach($queries as $key => $value) {
    if (instr($key, "_")) {
      //if (strrpos($key, "_") !== false) {
      $result = true;
      break;
    }
  }
  return $result;
}

function linkedImages($queries) {
  $result = false;
  foreach($queries as $key => $value) {
    if (instr($key, "_") && instr($key, "images")) {
      $result = true;
      break;
    }
  }
  return $result;
}

function deleteQuery($queries) {
  $result = false;
  foreach($queries as $key => $value) {
    if (instr($key, "deleteFlag")) {
      $result = true;
      break;
    }
  }
  return $result;
}

function columnIsNumeric($dbase, $tableName, $columnName) {
  $result = false;
  $query = "SELECT " . $columnName . " FROM " . $tableName;
  if ($queryResult = mysqli_query($dbase, $query)) {
    $columnData = mysqli_fetch_field_direct($queryResult, 0);
    $result = ($columnData->type > 0 && $columnData->type < 10) ? true : false;
    mysqli_free_result($queryResult);
  }
  return $result;
}

function tableNameValid ($dbase, $dbName, $tableName) {
  $result = false;
  $query = "SHOW TABLES FROM {$dbName}";
  $tableNames = mysqli_query($dbase, $query);
  if ($dbase && $tableNames) {
    while ($row = mysqli_fetch_row($tableNames)) {
      if ($row[0] == $tableName) {
        $result = true;
        break;
      }
    }
    mysqli_free_result($tableNames);
  }

  $dbError = mysqli_error($dbase);
  return $result;
}

function columnNamesValid($dbase, $tableName, $fieldNames) {  //~clv
  $result = false;
  $matchCount = 0;
  $fieldCount = count($fieldNames);

  if ($dbase && $fieldCount > 0) {
    $query = "SHOW COLUMNS FROM {$tableName}";
    $columnNames = mysqli_query($dbase, $query);
    if ($columnNames) {
      while ($row = mysqli_fetch_row($columnNames)) {
        foreach ($fieldNames as $fieldName => $value) {
          if (instr($fieldName, "="))
            $fieldName = explode("=", $fieldName)[0];
          if ($row[0] == $fieldName) {
            $matchCount++;
            break;
          }
        }
        if ($matchCount == $fieldCount) break;
      }
      mysqli_free_result($columnNames);
    }
    $result = ($matchCount == $fieldCount);
  }
  $dbError = mysqli_error($dbase);
  return $result;
}

function columnNamesValidate($dbase, $tableName, &$fieldNames) {  //~clvt
  $result = false;
  $fieldCount = count($fieldNames);

  if ($dbase && $fieldCount > 0) {
    $query = "SHOW COLUMNS FROM {$tableName}";
    $queryResult = mysqli_query($dbase, $query);

    if ($queryResult) {
      $i = 0; $columnNames = array();
      while ($row = mysqli_fetch_row($queryResult)) {
        $columnNames[$i] = $row[0]; $i++;
      }
      mysqli_free_result($queryResult);
      $result = true;

      foreach ($fieldNames as $fieldName => $value) {
        $matchFound = false;
        if (instr($fieldName, "="))
          $fieldName = explode("=", $fieldName)[0];
        for ($i = 0; $i < sizeof($columnNames); $i++) {
          if ($columnNames[$i] == $fieldName) {
            $matchFound = true;
            break;
          }
        }
        if (!$matchFound) {
          unset($fieldNames[$fieldName]);
        }
      }
    }
  }
  $dbError = mysqli_error($dbase);
  return $result;
}

?>