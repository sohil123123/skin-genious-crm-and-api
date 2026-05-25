pipeline {
    agent any
    tools {nodejs "node24.9.0"}
    stages {
        stage("build"){
           steps {
                sh 'composer --version'
                sh 'php --version'
                sh 'node -v'
                sh 'npm -v'
                sh 'npm ci'  // Changed: Use ci for CI; installs from package-lock.json
                sh 'composer install -n'  // Now runs after manifest exists
                sh 'npm run build'  // Builds Vite assets (manifest.json)
            }
        }
        stage("Populate .env file") {
            steps {
                withCredentials([file(credentialsId: 'production_crm_env', variable: 'mySecretEnvFile')]){
                    sh 'cp -rf $mySecretEnvFile $WORKSPACE/.env'
                }
                // sh 'php artisan test'
            }
        }
        // stage("test perform")
        // {
        //     steps{
        //         sh './vendor/bin/pest'
        //     }
        // }
        stage("Verify SSH connection to server") {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
                        ssh -i ~/.ssh/id_ed25519_deploy -o StrictHostKeyChecking=no root@187.127.173.33 whoami
                    '''
                }
            }
        }

    }
    post {
        success{
            withCredentials([sshUserPrivateKey(credentialsId: "jenkins", keyFileVariable: 'keyfile')]) {
              sh  'rsync -vrzhe "ssh -o StrictHostKeyChecking=no -i ~/.ssh/id_ed25519_deploy" . root@187.127.173.33:/home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in'
            }

            sshagent(credentials: ['jenkins']) {
                sh '''
                    ssh -i ~/.ssh/id_ed25519_deploy -o StrictHostKeyChecking=no root@187.127.173.33 << EOF
                    whoami
                    cd /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in
                    php --version
                    chown www-data:www-data /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in/storage -R
                    chown www-data:www-data /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in/bootstrap -R
                    chmod -R 0777 /home/ai-aesthetics-crm/htdocs/crm.ai-aesthetics.in/storage
                    php artisan migrate --force --no-interaction
                    php artisan shield:generate --panel=admin --all --no-interaction
                    php artisan config:cache
                    php artisan storage:link
                <<EOF '''
            }
        }
        always {
            cleanWs(cleanWhenNotBuilt: false,
                deleteDirs: true,
                disableDeferredWipeout: true,
                notFailBuild: true,
                patterns: [[pattern: '.gitignore', type: 'INCLUDE']])
        }
    }
}
