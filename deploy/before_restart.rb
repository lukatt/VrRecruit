application_env = new_resource.environment['APPLICATION_ENV']

php_app_name, node_deploy = node[:deploy].first

node[:deploy].each do
  composer_opt = '--no-dev' if ['production'].include? application_env
  deploy_to = node_deploy["deploy_to"]

  script "install_composer" do
    Chef::Log.info("Installing composer and all the PHP dependencies...")
    Chef::Log.info("\t[!] Still running, this could take a while...")

    interpreter "bash"
    user "root"
    cwd "#{release_path}"
    code <<-EOH
    curl -s https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    export COMPOSER_PROCESS_TIMEOUT=6000
    composer install --no-interaction --prefer-dist -o #{composer_opt}
    EOH
    only_if { ::File.exists?("#{release_path}/composer.json") }
  end

  script "symlink_vagrant_shared" do
    interpreter "bash"
    user "root"
    cwd "#{release_path}"
    code <<-EOH
    ln -sfn /vagrant #{deploy_to}/current
    EOH
    only_if { Kernel::system('virt-what|grep -q virtualbox') }
  end

end
