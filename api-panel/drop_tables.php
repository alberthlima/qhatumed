<?php
DB::statement('SET FOREIGN_KEY_CHECKS=0');
Schema::dropIfExists('cash_movements');
Schema::dropIfExists('cash_register_sessions');
Schema::dropIfExists('cash_registers');
DB::statement('SET FOREIGN_KEY_CHECKS=1');
DB::table('migrations')->where('migration', 'like', '%cash%')->delete();
echo "Tables dropped\n";
