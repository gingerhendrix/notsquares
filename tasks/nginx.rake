
namespace :nginx do

  desc "Install nginx config" 
  remote_task :install do
    run "sudo cp #{DEPLOY_ROOT}/conf/production/notsquares.nginx.conf /etc/nginx/sites-available/notsquares.nginx.conf"
    run "sudo ln -sf /etc/nginx/sites-available/notsquares.nginx.conf /etc/nginx/sites-enabled/notsquares.nginx.conf"
  end
  
  desc "Restart nginx"
  remote_task :restart do
    run "sudo /etc/init.d/nginx restart"
  end

end
