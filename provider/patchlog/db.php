<?php
namespace provider\patchlog;

   use lib\provider\baseprovider\BasePatchlogProvider;

   class Db extends BasePatchlogProvider{
       function setup()
       {
           $table = $this->patch->table("hodpatch");
           if (!$table->exists()) {
               $table->addField("patch", "varchar(50)");
               $table->addField("success", "int");
               $table->addField("date", "int");
               $table->create();
           }
       }
       function save($patchModel)
       {
           $this->db->saveModel($patchModel, "hodpatch");
       }
       function needPatch($name)
       {
           $query = $this->db->query("select id from hodpatch where patch='" . $name . "' and success=1");
           return !$this->db->numRows($query);
       }
   }
?>