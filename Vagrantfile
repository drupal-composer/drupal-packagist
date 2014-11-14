Vagrant.configure('2') do |config|
  config.vm.box = 'precise64'
  config.vm.network :private_network, ip: '10.68.33.10'
  config.vm.provider :virtualbox do |vb|
    vb.customize ["modifyvm", :id, "--memory", "2048"]
  end
  config.vm.synced_folder '.', '/home/vagrant/packagist', :nfs => true

  config.omnibus.chef_version = :latest
  config.berkshelf.enabled = true

  config.vm.provision :chef_client do |chef|
    chef.chef_server_url = "https://chef.willmilton.com"
    chef.validation_key_path = "~/.chef/chef-validator.pem"
    chef.add_recipe 'build-essential'
    chef.add_role 'db_master'
    chef.add_recipe 'packagist::redis'
    chef.add_recipe 'packagist::solr'
    chef.add_recipe 'packagist::rabbitmq'
    chef.add_recipe 'packagist::bootstrap_app'
    chef.add_recipe 'packagist::app_worker'
    chef.json = {
      packagist: {
        web_root: '/var/www',
        ref: 'bg-updates',
        repository: 'https://github.com/winmillwill/packagist',
        nelmio_solarium: {
          clients: {
            default: {
              dsn: 'http://localhost:8080/solr/packagist'
            }
          }
        }
      },
      mysql: {
        server_root_password: 'password',
        server_repl_password: 'password',
        server_debian_password: 'password'
      }
    }
  end
end
