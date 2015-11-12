application_env = new_resource.environment['APPLICATION_ENV']

node[:deploy].each do |application, deploy|
  composer_opt = '--no-dev' if ['production'].include? application_env
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
end

