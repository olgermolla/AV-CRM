
<?php include_once 'header.php'; ?>
 


  <div class="content-wrapper">
    <section class="content">
 

<?php 

if (!isset($_GET['pager'])) {
                $pager='home';
        } else{
          $pager=$_GET['pager'];
        }


        switch ($pager) {
          case 'home':
            $_GET['pager']='home';
              include 'operator_load.php'; 
            break;

            case 'potential':
            $_GET['pager']='potential';
              include 'operator_load.php'; 
            break;

            case 'followup':
            $_GET['pager']='followup';
              include 'operator_load.php'; 
              $f=' active';
            break;

            case 'interested':
            $_GET['pager']='interested';
              include 'operator_load.php'; 
            break;

            case 'noninterested':
            $_GET['pager']='noninterested';
              include 'operator_load.php'; 
            break;
             case 'nonanswer':
            $_GET['pager']='nonanswer';
              include 'operator_load.php'; 
            break;

            case 'callfailed':
            $_GET['pager']='callfailed';
              include 'operator_load.php'; 
            break;
               case 'secretary':
            $_GET['pager']='secretary';
              include 'operator_load.php'; 
            break;

            case 'new':
            $_GET['pager']='new';
              include 'operator_load.php'; 
            break;
           
            
          default:
            
            break;
        }
?>
              

    </section>

    <?php include('menu_operator.php') ?>
    
  </div>

<?php  include('footer.php') ?>
