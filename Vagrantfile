
Vagrant.configure("2") do |config|
  config.vm.box = "Vreasy/vrrecruit"
  config.vm.hostname = "#{`hostname`[0..-2]}".sub(/\..*$/,'')+"-va-opsworks"

  config.vm.provider "virtualbox" do |v|
    v.customize ["modifyvm", :id, "--cpuexecutioncap", "90"]
    v.cpus = 2
    v.memory = 512
  end

  # Create the php-app layer
  config.vm.define "app" do |layer|

    layer.vm.provision "opsworks", type:"shell", args:[
      'deploy/vagrant/stack.json',
      'deploy/vagrant/php-app.json'
    ]

    # Forward port 80 so we can see our work
    layer.vm.network "forwarded_port", guest: 80, host: 8011
    layer.vm.network "private_network", ip: "10.10.10.10"
  end
end
