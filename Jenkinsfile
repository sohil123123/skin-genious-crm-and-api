pipeline {
    agent any
    tools { nodejs "node24.9.0" }
    
    stages {
        stage('Build') {
            steps {
                sh 'npm ci'
                sh 'composer install -n --optimize-autoloader --no-dev'
                sh 'npm run build'
            }
        }
        
        stage('Populate .env') {
            steps {
                withCredentials([file(credentialsId: 'production_crm_env', variable: 'mySecretEnvFile')]) {
                    sh 'cp -f $mySecretEnvFile .env'
                }
            }
        }
    }
    
    post {
        success {
            sshagent(credentials: ['jenkins']) {
                sh '''
                    echo "=== Starting Deployment ==="
                    
                    # Deploy files using rsync to localhost
                    rsync -vrz --delete --exclude='.git' --exclude='storage/framework/' \
                        -e "ssh -o StrictHostKeyChecking=no" \
                        . root@localhost:/home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in
                    
                    # Run post-deployment commands
                    ssh -o StrictHostKeyChecking=no root@localhost << 'EOF'
                        cd /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in
                        
                        echo "Running Laravel commands..."
                        php artisan config:clear
                        php artisan cache:clear
                        php artisan view:clear
                        php artisan route:clear
                        
                        php artisan migrate --force --no-interaction
                        php artisan shield:generate --panel=admin --all --no-interaction || true
                        
                        php artisan storage:link
                        php artisan config:cache
                        php artisan route:cache
                        php artisan view:cache
                        
                        chown -R www-data:www-data storage bootstrap/cache
                        chmod -R 775 storage bootstrap/cache
                        
                        echo "✅ Deployment Completed Successfully!"
                    EOF
                '''
            }
        }
        
        always {
            cleanWs()
        }
    }
}