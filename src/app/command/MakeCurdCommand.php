<?php

namespace app\command;

use Doctrine\Inflector\InflectorFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Webman\Console\Util;


class MakeCurdCommand extends Command
{
    protected static $defaultName = 'make:curd';
    protected static $defaultDescription = '使用命令快速CURD代码';

    /**
     * @return void
     */
    protected function configure(): void
    {
        // 应用名称
        $this->addArgument('name', InputArgument::REQUIRED, '必填:请输入你的应用名称(小写输入多个单词用-分隔)');
        $this->addArgument('model_name', InputArgument::OPTIONAL, '可选:自定义数据表模型名称');
        // 控制器参数
        $this->addArgument('controller_name', InputArgument::OPTIONAL, '可选:自定义控制器名称以及指定路径');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 创建数据库模型
        $model = $this->createModel($input, $output);
        // 创建数据访问层
        $mapper = $this->createMapper($input, $output, $model);
        // 创建服务层
        $service = $this->createService($input, $output, $mapper);
        // 创建请求验证层
        $request = $this->createRequest();
        // 创建控制器
        $this->createController($input, $output, $service);
        return self::SUCCESS;
    }

    protected function createModel(InputInterface $input, OutputInterface $output): array
    {
        $name = $input->getArgument('model_name');
        if (!$name) {
            $name = $input->getArgument('name');
        }
        $name = Util::nameToClass($name);
        $output->writeln("Make model $name");
        if (!($pos = strrpos($name, '/'))) {
            $name = ucfirst($name);
            $model_str = Util::guessPath(app_path(), 'model') ?: 'model';
            $file = app_path() . "/$model_str/$name.php";
            $namespace = $model_str === 'Model' ? 'App\Model' : 'app\model';
        } else {
            $name_str = substr($name, 0, $pos);
            if ($real_name_str = Util::guessPath(app_path(), $name_str)) {
                $name_str = $real_name_str;
            } else if ($real_section_name = Util::guessPath(app_path(), strstr($name_str, '/', true))) {
                $upper = strtolower($real_section_name[0]) !== $real_section_name[0];
            } else if ($real_base_controller = Util::guessPath(app_path(), 'controller')) {
                $upper = strtolower($real_base_controller[0]) !== $real_base_controller[0];
            }
            $upper = $upper ?? strtolower($name_str[0]) !== $name_str[0];
            if ($upper && !$real_name_str) {
                $name_str = preg_replace_callback('/\/([a-z])/', function ($matches) {
                    return '/' . strtoupper($matches[1]);
                }, ucfirst($name_str));
            }
            $path = "$name_str/" . ($upper ? 'Model' : 'model');
            $name = ucfirst(substr($name, $pos + 1));
            $file = app_path() . "/$path/$name.php";
            $namespace = str_replace('/', '\\', ($upper ? 'App/' : 'app/') . $path);
        }
        $this->createModelEnd($name, $namespace, $file);
        return [$name, $namespace, $file];
    }

    /**
     * @param $class
     * @param $namespace
     * @param $file
     * @return void
     */
    protected function createModelEnd($class, $namespace, $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $table = Util::classToName($class);
        $table_val = 'null';
        $pk = 'id';
        $properties = '';
        try {
            $prefix = config('database.connections.mysql.prefix') ?? '';
            $database = config('database.connections.mysql.database');
            $inflector = InflectorFactory::create()->build();
            $table_plura = $inflector->pluralize($inflector->tableize($class));
            if (\support\Db::select("show tables like '{$prefix}{$table_plura}'")) {
                $table_val = "'$table'";
                $table = "{$prefix}{$table_plura}";
            } else if (\support\Db::select("show tables like '{$prefix}{$table}'")) {
                $table_val = "'$table'";
                $table = "{$prefix}{$table}";
            }
            $tableComment = \support\Db::select('SELECT table_comment FROM information_schema.`TABLES` WHERE table_schema = ? AND table_name = ?', [$database, $table]);
            if (!empty($tableComment)) {
                $comments = $tableComment[0]->table_comment ?? $tableComment[0]->TABLE_COMMENT;
                $properties .= " * {$table} {$comments}" . PHP_EOL;
            }
            foreach (\support\Db::select("select COLUMN_NAME,DATA_TYPE,COLUMN_KEY,COLUMN_COMMENT from INFORMATION_SCHEMA.COLUMNS where table_name = '$table' and table_schema = '$database' ORDER BY ordinal_position") as $item) {
                if ($item->COLUMN_KEY === 'PRI') {
                    $pk = $item->COLUMN_NAME;
                    $item->COLUMN_COMMENT .= "(主键)";
                }
                $type = $this->getType($item->DATA_TYPE);
                $properties .= " * @property $type \${$item->COLUMN_NAME} {$item->COLUMN_COMMENT}\n";
            }
        } catch (\Throwable $e) {
        }
        $properties = rtrim($properties) ?: ' *';
        $model_content = <<<EOF
<?php

namespace $namespace;

use app\AbstractModel;

/**
$properties
 */
class $class extends AbstractModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected \$table = $table_val;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected \$primaryKey = '$pk';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public \$timestamps = false;
    
    
}

EOF;
        file_put_contents($file, $model_content);
    }


    /**
     * @param string $type
     * @return string
     */
    protected function getType(string $type): string
    {
        if (str_contains($type, 'int')) {
            return 'integer';
        }
        return match ($type) {
            'varchar', 'string', 'text', 'date', 'time', 'guid', 'datetimetz', 'datetime', 'decimal', 'enum' => 'string',
            'boolean' => 'integer',
            'float' => 'float',
            default => 'mixed',
        };
    }


    protected function createMapper($input, $output, array $model): array
    {
        $name = $input->getArgument('name');
        $name = Util::nameToClass($name);
        $output->writeln("Make mapper $name");
        $name .= "Mapper";
        $name = ucfirst($name);
        $mapper_str = Util::guessPath(app_path(), 'mapper') ?: 'mapper';
        $file = app_path() . "/$mapper_str/$name.php";
        $namespace = $mapper_str === 'Mapper' ? 'App\Mapper' : 'app\mapper';
        $this->createMapperEnd($name, $namespace, $file, $model);
        return [$name, $namespace, $file];
    }

    protected function createMapperEnd(string $name, string $namespace, string $file, array $data): void
    {
        $model_name = $data[0];
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $mapper_content = <<<EOF
<?php

namespace $namespace;

use app\AbstractMapper;
use $data[1]\\$data[0];

class $name extends AbstractMapper
{
    public \support\Model \$model;
    
    public function assignModel(): void
    {
        \$this->model = new $model_name;
    }

}

EOF;
        file_put_contents($file, $mapper_content);
    }

    protected function createService(InputInterface $input, OutputInterface $output, array $mapper): array
    {
        $name = $input->getArgument('name');
        $name = Util::nameToClass($name);
        $output->writeln("Make service $name");
        $name = ucfirst($name);
        $mapper_str = Util::guessPath(app_path(), 'service') ?: 'service';
        $impl_name = $name;
        $name .= 'Interface';
        $file = app_path() . "/$mapper_str/$name.php";
        $impl_file = app_path() . "/$mapper_str/impl/$impl_name.php";
        $namespace = $mapper_str === 'Service' ? 'App\Service' : 'app\service';
        $impl_namespace = $namespace . '\impl';
        $this->createServiceEnd($name, $impl_name, $namespace, $impl_namespace, $file, $impl_file, $mapper);
        return [$impl_name, $impl_namespace, $impl_file];
    }

    private function createServiceEnd(string $name, string $impl_name, string $namespace, string $impl_namespace, string $file, string $impl_file, array $data)
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $interface_content = <<<EOF
<?php

namespace $namespace;

use support\Request;

interface $name
{
    /**
     * @param Request \$request
     * @return array
     */
    public function retrieve(Request \$request): array;

    /**
     * @param Request \$request
     * @return array
     */
    public function create(Request \$request): array;

    /**
     * @param Request \$request
     * @return array
     */
    public function update(Request \$request): array;

    /**
     * @param Request \$request
     * @return array
     */
    public function delete(Request \$request): array;
}

EOF;
        file_put_contents($file, $interface_content);


        $impl_path = pathinfo($impl_file, PATHINFO_DIRNAME);
        if (!is_dir($impl_path)) {
            mkdir($impl_path, 0777, true);
        }
        $impl_content = <<<EOF
<?php
namespace $impl_namespace;

use app\AbstractService;
use $data[1]\\$data[0];
use $namespace\\$name;

class $impl_name extends AbstractService implements $name
{
    public \app\AbstractMapper \$mapper;

    public function assignMapper(): void
    {
        \$this->mapper = new $data[0];
    }
}

EOF;
        file_put_contents($impl_file, $impl_content);
    }

    protected function createController(InputInterface $input, OutputInterface $output,array $data): void
    {
        $name = $input->getArgument('controller_name');
        if (!$name) {
            $name = $input->getArgument('name');
        }
        $output->writeln("Make controller $name");
        // 获取名称并转成权限标识
        $small_name = $input->getArgument('name');
        $replacement = ":";
        if (str_contains($small_name, "-")) {
            $newString = str_replace("-", $replacement, $small_name);
            $small_name = $newString;
        }
        // 获取权限标识前缀将他转成路由
        $router = $small_name;
        $replacement = "/";
        if (str_contains($router, ":")) {
            $router = str_replace(":", $replacement, $router);
        }
        $router = "/$router";
        // 是否开启控制器后缀
        $suffix = config('app.controller_suffix', '');
        if ($suffix && !strpos($name, $suffix)) {
            $name .= $suffix;
        }
        // 将名称转为类名
        $name = Util::nameToClass($name);
        $name = ucfirst($name);
        // 控制器文件夹地址
        $controller_str = Util::guessPath(app_path(), 'controller') ?: 'controller';
        $namespace_suffix = $router;
        if (str_contains($router, "/")) {
            $namespace_suffix = str_replace("/", '\\', $namespace_suffix);
        }
        var_dump($router);
        $parts = explode('/', $router);
        $file_path_suffix = implode('', array_slice($parts, 0, 2));
        var_dump($file_path_suffix);
        // 控制器地址
        $file = app_path() . "/$controller_str/$file_path_suffix/$name.php";
        // 命名空间
        $namespace = $controller_str === 'Mapper' ? 'App\Controller' : 'app\controller';
        $namespace .= $namespace_suffix;
        $this->createControllerEnd($name, $namespace, $file,$data,$small_name,$router);
    }

    /**
     * @param $name
     * @param $namespace
     * @param $file
     * @param $data
     * @param $small_name
     * @param $router
     * @return void
     */
    protected function createControllerEnd($name, $namespace, $file,$data,$small_name,$router): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $mapper_name = $this->convertToCamelCase($data[0]);
        $controller_content = <<<EOF
<?php

namespace $namespace;

use app\\AbstractController;
use app\\annotation\\Permission;
use app\\request\\SystemAccountRequest;
use LinFly\\Annotation\\Annotation\\Inject;
use LinFly\\Annotation\\Route\\Controller;
use LinFly\\Annotation\\Route\Route;
use LinFly\\Annotation\\Validate\\Validate;
use support\\Request;
use $data[1]\\$data[0];

#[Controller(prefix: '$router')]
#[Validate(validate: SystemAccountRequest::class)]
class $name extends AbstractController
{
    #[Inject]
    protected $data[0] $$mapper_name;

    #[Route(path: 'retrieve', methods: ['GET', 'OPTIONS'])]
    #[Permission('$small_name:retrieve')]
    public function retrieve(Request \$request): \support\Response
    {
        return \$this->success(\$this->{$mapper_name}->retrieve(\$request));
    }

    #[Route(path: 'create', methods: ['PUT', 'OPTIONS'])]
    #[Permission('$small_name:create')]
    public function create(Request \$request): \support\Response
    {
        return \$this->success(\$this->{$mapper_name}->create(\$request));
    }

    #[Route(path: 'update', methods: ['PUT', 'OPTIONS'])]
    #[Permission('$small_name:update')]
    public function update(Request \$request): \support\Response
    {
        return \$this->success(\$this->{$mapper_name}->update(\$request));
    }

    #[Route(path: 'delete', methods: ['DELETE', 'OPTIONS'])]
    #[Permission('$small_name:delete')]
    public function delete(Request \$request): \support\Response
    {
        return \$this->success(\$this->{$mapper_name}->delete(\$request));
    }

}

EOF;
        file_put_contents($file, $controller_content);
    }

    public function convertToCamelCase($str): array|string
    {
        $str = lcfirst($str); // 将首字母转为小写
        $pattern = '/([A-Z])/'; // 匹配大写字母
        $replacement = '_$1'; // 在大写字母前添加下划线
        $str = preg_replace($pattern, $replacement, $str);
        $str = str_replace('_', '', $str); // 移除下划线
        return $str;
    }
}
