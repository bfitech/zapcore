Contributing to Zap\*
====================


Found a bug? Help us fix it.


## 0. General Workflow

0.  Fork from Github web interface.

1.  Clone your fork and load dependencies:

    ```txt
    $ git clone git@github.com:${YOUR_GITHUB_USERNAME}/zapcore.git
    $ cd zapcore
    $ composer -vvv install -no
    ```

2.  Make your changes. Sources are in `./src` and occasionally also
    in `./dev` directories.

    ```txt
    $ # do your thing, e.g.:
    $ vim src/Router.php
    ```

3.  Adjust the tests. Make sure there's no failure. Tests are in
    `./tests` directory.

    ```txt
    $ # adjust tests, e.g.:
    $ vim tests/RouterTest.php
    $ # run tests
    $ phpunit || echo 'Boo!'
    ```

4.  Make sure code coverage is at 100% or close. If you have
    [Xdebug](https://xdebug.org/) installed, coverage report is
    available with:

    ```txt
    $ phpunit
    $ x-www-browser docs/coverage/index.html
    ```

5. Push to your fork and submit a Pull Request.


## 1. Coding Style

With C programming language replaced by PHP, use
[Linux kernel coding style](https://web.archive.org/web/20120421035445/http://www.kernel.org:80/doc/Documentation/CodingStyle)
as the reference. These following exceptions and additions apply:

0.  Curly braces are K&R through and through, either in class blocks,
    function/method blocks, or control structures, e.g.:

    ```php
    public function __construct(
        $param1=null, $param2=null, $param3=1, $param4=1e3,
        Logger $param5=null
    ) {
        if ($this->param1 != $param1) {
            $this->set_param($param1);
            parent::__construct();
        }
        $this->param2 = $param2 < $param3 || $param2 > $param4
            ? $param3 : $param2;
        $this->param5 = $param5;
    }

    private function set_param($param1) {
        if ($param1)
            $this->param1 = $param1;
    }
    ```
1.  Case blocks in a switch statement are indented, e.g.:

    ```php
    switch ($param1) {
        case CONST1:
        case CONST2:
            do_something();
            break;
        case CONST3:
            do_another_thing();
            break;
        case null:
        default:
            self::halt();
    }
    ```

2.  Split long conditionals into sensible multiple lines by their
    logical operators, e.g.:

    ```php
    if (
        $param1 == $this->param1 &&
        $param2 != $this->param2 &&
        stripos($param1, $param2) !== false
    ) {
        $this->param2 = $param2;
    }
    ```

3.  Tabs for indentation, space for alignment. Tab width is 4
    characters. Line width is 72 characters max. No trailing
    whitespaces at the end of a line. Add at least 1 LF at the end of a
    file.

4.  Commit messages are max 50 characters of good imperative English
    sentence on the first line, and max 72 characters on subsequent
    lines, e.g.:

    ```txt
    Fix path parser.

    Long parameters in path parser was known to break when a non-slash
    character precedes open curly braces.
    ```

