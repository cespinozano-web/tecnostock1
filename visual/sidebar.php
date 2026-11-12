<?php
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>


<div class="msb" id="msb">
    <nav class="navbar navbar-default" role="navigation">
        <div class="navbar-header">
            <div class="brand-wrapper">
                <div class="brand-name-wrapper">
                    <a class="navbar-brand" href="inicio.php">TecnoStock</a>
                </div>
            </div>
        </div>

        <div class="side-menu-container">
            <ul class="nav navbar-nav">

                <li class="<?php echo ($pagina_actual == 'inicio.php') ? 'active' : ''; ?>">
                    <a href="inicio.php"><i class="fa fa-home"></i> Inicio</a>
                </li>

                <li class="<?php echo ($pagina_actual == 'historial.php') ? 'active' : ''; ?>">
                    <a href="historial.php"><i class="fa fa-history"></i> Historial</a>
                </li>
                
                <li class="<?php echo ($pagina_actual == 'bodega.php') ? 'active' : ''; ?>">
                    <a href="bodega.php"><i class="fa fa-cubes"></i> Bodega</a>
                </li>

                <li class="panel panel-default" id="dropdown">
                    <a data-toggle="collapse" href="#dropdown-lvl1">
                        <i class="fa fa-user"></i> Mi Perfil <span class="caret"></span>
                    </a>

                    <div id="dropdown-lvl1" class="panel-collapse collapse">
                        <div class="panel-body">
                            <ul class="nav navbar-nav">
                                <li><a href="misDatos.php">Mis Datos</a></li>
                                <li><a href="logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>   
</div>