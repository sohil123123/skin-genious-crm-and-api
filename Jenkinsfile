pipeline {
    agent any
    tools {nodejs "nodejs22"}
    stages {
        stage("build"){
            steps {
                sh 'composer --version'
                sh 'php --version'
                sh 'node -v'
                sh 'npm -v'
                sh 'composer install -n'
                sh 'npm install'
                sh 'npm run build'
            }
        }
        stage("Populate .env file") {
            steps {
                withCredentials([file(credentialsId: 'envSkinGeniousCRMApiBackend', variable: 'mySecretEnvFile')]){
                    sh 'cp -rf $mySecretEnvFile $WORKSPACE/.env'
                }
                sh 'php artisan test'
            }
        }
        stage("test perform")
        {
            steps{
                sh './vendor/bin/pest'
            }
        }
        stage("Verify SSH connection to server") {
            steps {
                sshagent(credentials: ['jenkins']) {
                    sh '''
                        ssh -i ~/.ssh/id_rsa -o StrictHostKeyChecking=no root@147.93.31.88 whoami
                    '''
                }
            }
        }

    }
    post {
        success{
            withCredentials([sshUserPrivateKey(credentialsId: "jenkins", keyFileVariable: 'keyfile')]) {
              sh  'rsync -vrzhe "ssh -o StrictHostKeyChecking=no -i ~/.ssh/id_rsa" . root@147.93.31.88:/home/cbphysiotherapy-skingeniouscrm/htdocs/skingeniouscrm.cbphysiotherapy.in'
            }

            sshagent(credentials: ['jenkins']) {
                sh '''
                    ssh -i ~/.ssh/id_rsa -o StrictHostKeyChecking=no root@147.93.31.88 << EOF
                    whoami
                    cd /home/cbphysiotherapy-skingeniouscrm/htdocs/skingeniouscrm.cbphysiotherapy.in
                    php --version
                    chown www-data:www-data /home/cbphysiotherapy-skingeniouscrm/htdocs/skingeniouscrm.cbphysiotherapy.in/storage -R
                    chown www-data:www-data /home/cbphysiotherapy-skingeniouscrm/htdocs/skingeniouscrm.cbphysiotherapy.in/bootstrap -R
                    chmod -R 0777 /home/cbphysiotherapy-skingeniouscrm/htdocs/skingeniouscrm.cbphysiotherapy.in/storage
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
