available_instances = new_resource.node[:opsworks][:layers]['php-app'][:instances]
available_instances = available_instances.select { |name, instance|
  ['requested', 'booting', 'running_setup', 'online'].include?(instance[:status])
}
leader_name = available_instances.keys.sort.first
leader = new_resource.node[:opsworks][:layers]['php-app'][:instances][leader_name]

if leader && new_resource.node[:opsworks][:instance]
  is_leader = leader[:aws_instance_id] == new_resource.node[:opsworks][:instance][:aws_instance_id]
else
  is_leader = true
end

application_env = new_resource.environment['APPLICATION_ENV']

php_app_name, node_deploy = node[:deploy].first
domain = if php_app_name then new_resource.node[:deploy][php_app_name][:domains].first else '' end

cli_ini_set = 'PHP_USER_INI=$(cat '+release_path+'/vreasy/public/.user.ini|tr "\n" ","|sed "s/,/ \-d /g"|tr -d "\n")nil=nil'
env = [
  "export APPLICATION_ENV=#{application_env}",
  "export HOME=$HOME",
  "export PHP_DB_DBNAME=#{new_resource.environment['PHP_DB_DBNAME']}",
  "export PHP_DB_HOST=#{new_resource.environment['PHP_DB_HOST']}",
  "export PHP_DB_USERNAME=#{new_resource.environment['PHP_DB_USERNAME']}",
  "export PHP_DB_PASSWORD=#{new_resource.environment['PHP_DB_PASSWORD']}",
].join('; ')

execute "ruckus_migrate" do
  cwd "#{release_path}"
  command "#{env}; #{cli_ini_set}; php -d $PHP_USER_INI vendor/ruckusing/ruckusing-migrations/ruckus.php db:migrate ENV=#{application_env}"
  environment new_resource.environment
  only_if { is_leader && application_env != 'test'}
  user "root"
  group "root"
  timeout 14400
end

execute "" do
  Chef::Log.info("Ensure a newer version of npm")

  user "root"
  cwd "#{release_path}"
  command "npm install --force -g npm@3.3.6"
end


production_assets = ['production', 'test'].include?(application_env)? '--production' : ''
execute "npm_install" do
  Chef::Log.info("Installing the front-end tools with npm...")
  Chef::Log.info("\t[!] This could take a while...")

  cwd "#{release_path}"
  command "npm install #{production_assets}"
  only_if { ::File.exists?("#{release_path}/npm-shrinkwrap.json") }
end

execute "bower_install" do
  Chef::Log.info("Installing the front-end dependencies with bower...")
  Chef::Log.info("\t(This could take a while...)")

  cwd "#{release_path}"
  command "node_modules/.bin/bower cache clean && node_modules/.bin/bower install"
  user "deploy"
  group "www-data"
  only_if { ::File.exists?("#{release_path}/bower.json") }
end

execute "gulp" do
  cwd "#{release_path}"
  command "node_modules/.bin/gulp #{production_assets} --test=no --watch=no && node_modules/.bin/gulp --app=vrCart #{production_assets} --test=no --watch=no"
  user "deploy"
  group "www-data"
  only_if { ::File.exists?("#{release_path}/Gulpfile.js") }
end
