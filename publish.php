<?php


class publish{

    private $conf;

    public function __construct($option)
    {
        $this->conf = array_merge($this->conf, $option);
    }

    /**
     * @param $branch
     * @param $repos
     * @param $workDir
     * @return string
     */
    public function checkoutCode($branch, $repos,$workDir)
    {
        $checkOutDir = rtrim($workDir).DIRECTORY_SEPARATOR.time();
        return  sprintf('git clone --branch=%s %s %s',$branch, $repos, $checkOutDir );
    }
    /**
     * 根据提供的发布分支和线上稳定分支获取待发布文件列表
     * 需要在仓库发布的分支目录下工作
     *
     * @param $releaseBranch
     * @param $onlineBranch
     * @return string
     */
    public function getFileList($releaseBranch, $onlineBranch, $workDir)
    {
        //对比差异文件
        $command = "cd {$workDir} && git diff $onlineBranch..{$releaseBranch} --stat";
        //去除git diff输出的最后一行统计数据,得到差异文件列表
        $command .= "| head -n -1";
        //文件列表列转行
        $command .= "|awk '{print $1}'|tr '\n' ' '";
        return $command;
    }

    /**
     * 将文件压缩到本机一个临时目录
     *
     * @param $files
     * @param string $tempDir
     * @param array $exclude
     * @return string
     */
    public function compressFiles($files, $tempDir='/tmp', $exclude=[])
    {
        $tarFile = $tempDir.DIRECTORY_SEPARATOR.date("YmdHis").'tar.gz';
        $excStr = '';
        foreach($exclude as $file){
            $excStr .= sprintf('--exclude=%s', $file);
        }
        return  sprintf('tar -czf %s %s  %s', $tarFile, $files,  $excStr);
    }

    /**
     * 备份远程主机文件
     * @todo 和本地压缩有较多的重复代码
     *
     * @param $target
     * @param $releaseDir
     * @param $backupDir
     * @param array $exclude
     * @return string
     */
    public function backupTarget($target, $releaseDir, $backupDir, $exclude=[])
    {
        $tarFile = rtrim($backupDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.date("YmdHis").'tar.gz';
        $excStr = '';
        foreach($exclude as $file){
            $excStr .= sprintf('--exclude=%s', $file);
        }
        //远程主机文件备份命令
        $command = sprintf('tar -czPf %s %s  %s', $tarFile, $releaseDir,  $excStr);
        //远程主机连接命令
        $loginCommand = sprintf('ssh %s@%s -p %s', $target['user'], $target['host'], $target['port']);
        return  $loginCommand.' '.$command;
    }

    /**
     * 将文件传输到目标主机的临时目录
     *
     * @param $file
     * @param $target
     * @param string $tempDir
     * @return array
     */
    public function transFile($file, $target,$tempDir='/tmp')
    {
        return  sprintf('scp -P %s  %s %s@%s:%s',$target['port'], $file, $target['user'], $target['host'], rtrim($tempDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    /**
     * 解压缩文件到发布目录
     *
     * @param $file
     * @param $releaseDir
     * @return string
     */
    public function releaseFile($file, $releaseDir)
    {
        return  sprintf('tar -xvf %s -C %s', $file, $releaseDir);
    }

    /**
     * 文件回滚
     *
     * @param $file
     * @param $releaseDIr
     * @return string
     */
    public function rollback($file, $releaseDIr)
    {
        return $this->releaseFile($file, $releaseDIr);
    }

    /**
     * 执行shell命令
     *
     * @param $command
     * @return array
     */
    public function executeCommand($command)
    {
        $return = exec($command, $out, $code);
        return ['code'=>$code, 'data'=>['out'=>$out,'return'=>$return]];
    }

    /**
     * 执行远程命令
     *
     * @param $command
     * @param $target
     * @return array
     */
    public function executeRemoteCommand($command, $target)
    {
        $connectRemote = sprintf('ssh %s@%s -p %s', $target['user'], $target['host'], $target['port']);
        $command = $connectRemote.' '.$command;
        return $this->executeCommand($command);
    }

    public function test()
    {
        $releaseBranch = 'bandit/20210723';
        $targets =[
            ['name'=>'app1','host'=>'47.104.150.123','user'=>'user01','port'=>9761],
            ['name'=>'app2','host'=>'47.104.150.123','user'=>'user01','port'=>9761],
        ];
        $app = [
            ['name'=>'deploy',
                'repos'=>'https://github.com/banditsmile/deploy.git',
                'onlineBranch'=>'master',
                'workDir'=>'/data/wwwroot/test/deploy',
                'exclude'=>['.git']
            ]
        ];
        $localCommand = $this->checkoutCode($releaseBranch, $app[0]['repos'], $app[0]['workDir']);
        $return = $this->executeCommand($localCommand);

        $localCommand = $this->getFileList($releaseBranch, $app[0]['onlineBranch'], $app[0]['workDir']);
        $return = $this->executeCommand($localCommand);
        $localCommand = $this->compressFiles();
    }
}