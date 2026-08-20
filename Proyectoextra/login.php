
<style>

:root{
    --color-primario:#111827;
    --color-primario-oscuro:#0f172a;
    --color-secundario:#38bdf8;
    --color-secundario-oscuro:#0ea5e9;
    --color-peligro:#ef4444;
    --color-advertencia:#f59e0b;
    --color-fondo:#0b1120;
    --color-fondo-secundario:#111827;
    --color-card:#1e293b;
    --color-borde:#334155;
    --color-texto:#f8fafc;
    --color-texto-secundario:#94a3b8;
    --sombra:0 4px 18px rgba(0,0,0,.35);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    background:var(--color-fondo);
    color:var(--color-texto);
    min-height:100vh;
}

.contenedor,
.sidebar-layout{
    width:100%;
    min-height:100vh;
}

.sidebar{
    width:100%;
    background:linear-gradient(90deg,var(--color-primario),var(--color-primario-oscuro));
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:1rem 2rem;
    position:sticky;
    top:0;
    z-index:999;
    box-shadow:var(--sombra);
    border-bottom:1px solid var(--color-borde);
}

.sidebar-header{
    display:flex;
    align-items:center;
    gap:1rem;
}

.sidebar-nav ul{
    list-style:none;
    display:flex;
    gap:1rem;
    flex-wrap:wrap;
}

.sidebar-link{
    text-decoration:none;
    color:var(--color-texto);
    padding:.7rem 1.1rem;
    border-radius:12px;
    transition:.25s ease;
    background:transparent;
    border:1px solid transparent;
    font-weight:bold;
}

.sidebar-link:hover,
.sidebar-link.active{
    background:rgba(255,255,255,.08);
    border-color:var(--color-secundario);
}

.sidebar-link.logout:hover{
    background:var(--color-peligro);
}

.sidebar-user{
    color:var(--color-texto-secundario);
    text-align:right;
}

.sidebar-main,
.main-content{
    padding:2rem;
    width:100%;
}

.card{
    background:var(--color-card);
    border:1px solid var(--color-borde);
    border-radius:18px;
    padding:1.5rem;
    margin-bottom:1.5rem;
    box-shadow:var(--sombra);
}

.formulario,
.formulario-grupo{
    display:flex;
    flex-direction:column;
    gap:.6rem;
    margin-bottom:1rem;
}

input,
select,
textarea{
    width:100%;
    padding:.9rem 1rem;
    border-radius:12px;
    border:1px solid var(--color-borde);
    background:#0f172a;
    color:var(--color-texto);
}

input:focus,
select:focus,
textarea:focus{
    outline:none;
    border-color:var(--color-secundario);
}

.boton{
    border:none;
    border-radius:12px;
    padding:.9rem 1.2rem;
    cursor:pointer;
    transition:.25s ease;
    font-weight:bold;
}

.boton-primario{
    background:var(--color-secundario);
    color:#001018;
}

.boton-secundario{
    background:#1d4ed8;
    color:#1e293b;
}

.boton:hover{
    transform:translateY(-2px);
}

.tabla-usuarios{
    width:100%;
    border-collapse:collapse;
    margin-top:1rem;
    overflow:hidden;
    border-radius:12px;
}

.tabla-usuarios th{
    background:#0f172a;
    color:var(--color-texto);
}

.tabla-usuarios td,
.tabla-usuarios th{
    padding:1rem;
    border:1px solid var(--color-borde);
}

.login-wrap{
    max-width:450px;
    margin:50px auto;
    padding:1rem;
}

.login-card{
    background:var(--color-card) !important;
    border:1px solid var(--color-borde);
    color:var(--color-texto);
}

.login-card p,
small,
.sidebar-rol{
    color:var(--color-texto-secundario);
}

.mensaje-vacio{
    color:var(--color-texto-secundario);
    padding:1rem 0;
}

@media(max-width:900px){
    .sidebar{
        flex-direction:column;
        gap:1rem;
        align-items:flex-start;
    }

    .sidebar-nav ul{
        width:100%;
    }

    .sidebar-link{
        display:block;
        width:100%;
        text-align:center;
    }

    .sidebar-user{
        text-align:left;
    }

    .sidebar-main{
        padding:1rem;
    }
}

</style>
<?php header('Location: index.php'); exit;
